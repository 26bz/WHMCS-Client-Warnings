<?php

/**
 * @author     26BZ
 * @license    MIT License
 * @copyright  (c) 2025 26BZ - https://26bz.online/
 */

if (!defined("WHMCS")) {
  exit("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use WHMCS\View\Menu\Item as MenuItem;

add_hook('ClientAreaPage', 1, function ($vars) {
  if (!isset($_SESSION['uid']) || empty($_SESSION['uid'])) {
    return $vars;
  }

  $userId = (int)$_SESSION['uid'];

  $moduleActive = Capsule::table('tbladdonmodules')
    ->where('module', 'clientwarnings')
    ->where('setting', 'enforceAcknowledgment')
    ->value('value');

  if ($moduleActive != 'on') {
    return $vars;
  }

  $unacknowledgedWarnings = Capsule::table('mod_clientwarnings')
    ->where('user_id', $userId)
    ->where('acknowledged', 0)
    ->where('archived', 0)
    ->whereRaw('(expiration_date IS NULL OR expiration_date > NOW())')
    ->count();

  if ($unacknowledgedWarnings > 0) {
    if (isset($_GET['m']) && $_GET['m'] == 'clientwarnings') {
      return $vars;
    }

    if (isset($_GET['action']) && $_GET['action'] == 'logout') {
      return $vars;
    }

    header("Location: index.php?m=clientwarnings");
    exit;
  }

  return $vars;
});


add_hook('ClientAreaPrimaryNavbar', 1, function (MenuItem $primaryNavbar) {
  if (!isset($_SESSION['uid']) || empty($_SESSION['uid'])) {
    return;
  }

  $userId = (int)$_SESSION['uid'];

  $warningsCount = Capsule::table('mod_clientwarnings')
    ->where('user_id', $userId)
    ->where('archived', 0)
    ->whereRaw('(expiration_date IS NULL OR expiration_date > NOW())')
    ->count();

  if ($warningsCount > 0) {
    $unacknowledgedCount = Capsule::table('mod_clientwarnings')
      ->where('user_id', $userId)
      ->where('acknowledged', 0)
      ->where('archived', 0)
      ->whereRaw('(expiration_date IS NULL OR expiration_date > NOW())')
      ->count();

    $menuItem = $primaryNavbar->addChild(
      'clientWarnings',
      [
        'name' => 'Warnings',
        'label' => 'Warnings',
        'uri' => 'index.php?m=clientwarnings',
        'order' => 99
      ]
    );

    if ($unacknowledgedCount > 0) {
      $menuItem->setIcon('fa-exclamation-circle')->setBadge(
        $unacknowledgedCount,
        'danger'
      );
    } else {
      $menuItem->setIcon('fa-exclamation-circle');
    }
  }
});

add_hook('ClientAreaRegister', 1, function () {
  return [
    'clientwarnings' => [
      'callback' => 'clientwarnings_clientarea'
    ]
  ];
});


add_hook('ClientAreaFooterOutput', 1, function ($vars) {
  if (!isset($_SESSION['uid']) || empty($_SESSION['uid'])) {
    return;
  }

  if (isset($_GET['m']) && $_GET['m'] == 'clientwarnings') {
    return;
  }

  $userId = (int)$_SESSION['uid'];

  $moduleActive = Capsule::table('tbladdonmodules')
    ->where('module', 'clientwarnings')
    ->where('setting', 'enforceAcknowledgment')
    ->value('value');

  if ($moduleActive != 'on') {
    return;
  }

  $unacknowledgedWarnings = Capsule::table('mod_clientwarnings')
    ->where('user_id', $userId)
    ->where('acknowledged', 0)
    ->where('archived', 0)
    ->whereRaw('(expiration_date IS NULL OR expiration_date > NOW())')
    ->count();

  if ($unacknowledgedWarnings > 0) {
    return '
<div class="modal fade" id="warningNotificationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content panel-warning">
            <div class="modal-header panel-heading">
                <h4 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Important Account Notice</h4>
            </div>
            <div class="modal-body">
                <p>Your account has ' . $unacknowledgedWarnings . ' pending warning' . ($unacknowledgedWarnings != 1 ? 's' : '') . ' that require your acknowledgment.</p>
                <p>Please review and acknowledge these warnings to continue using all account features.</p>
            </div>
            <div class="modal-footer">
                <a href="index.php?m=clientwarnings" class="btn btn-primary">View Warnings</a>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $("#warningNotificationModal").modal("show");
});
</script>';
  }

  return '';
});


add_hook('DailyCronJob', 1, function () {
  $updated = Capsule::table('mod_clientwarnings')
    ->where('archived', 0)
    ->whereNotNull('expiration_date')
    ->where('expiration_date', '<', date('Y-m-d H:i:s'))
    ->update(['archived' => 1]);

  if ($updated > 0 && function_exists('logActivity')) {
    logActivity("ClientWarnings: Auto-archived {$updated} expired warnings");
  }
});
