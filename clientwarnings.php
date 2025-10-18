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

if (isset($_GET['action']) && $_GET['action'] === 'getWarningDetails') {
  $warningId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

  if ($warningId > 0) {
    $warning = Capsule::table('mod_clientwarnings')
      ->join('tblclients', 'mod_clientwarnings.user_id', '=', 'tblclients.id')
      ->select(
        'mod_clientwarnings.*',
        'tblclients.firstname',
        'tblclients.lastname',
        'tblclients.email'
      )
      ->where('mod_clientwarnings.id', $warningId)
      ->first();

    if ($warning) {
      $statusClass = '';
      $statusText = '';

      if ($warning->archived) {
        $statusClass = 'default';
        $statusText = 'Archived';
      } elseif ($warning->acknowledged) {
        $statusClass = 'success';
        $statusText = 'Acknowledged';
      } else {
        $statusClass = 'warning';
        $statusText = 'Pending';
      }

      if (!empty($warning->expiration_date) && strtotime($warning->expiration_date) < time()) {
        $statusClass = 'default';
        $statusText = 'Expired';
      }

      $severityClass = '';
      switch (strtolower($warning->severity)) {
        case 'minor':
          $severityClass = 'info';
          break;
        case 'major':
          $severityClass = 'warning';
          break;
        case 'critical':
          $severityClass = 'danger';
          break;
        default:
          $severityClass = 'default';
      }

      $attachmentsHtml = '';
      if (!empty($warning->attachments)) {
        $attachments = json_decode($warning->attachments, true);
        if (is_array($attachments) && count($attachments) > 0) {
          $attachmentsHtml = '<tr><th>Attachments</th><td><div class="row">';

          foreach ($attachments as $index => $attachment) {
            $fileName = htmlspecialchars($attachment['name']);
            $fileType = $attachment['type'];
            $filePath = 'addonmodules.php?module=clientwarnings&action=adminfile&warning=' . $warning->id . '&file=' . $index;

            if (strpos($fileType, 'image/') === 0) {
              $attachmentsHtml .= '
                <div class="col-md-4 col-sm-6 margin-bottom-10">
                    <div class="thumbnail">
                        <a href="' . $filePath . '" target="_blank">
                            <img src="' . $filePath . '" class="img-responsive" style="max-height: 150px;" alt="' . $fileName . '">
                        </a>
                        <div class="caption text-center">
                            <small>' . $fileName . '</small>
                        </div>
                    </div>
                </div>';
            } else {
              $attachmentsHtml .= '
                <div class="col-md-6 margin-bottom-10">
                    <a href="' . $filePath . '" target="_blank" class="btn btn-default btn-block">
                        <i class="fa fa-download"></i> ' . $fileName . '
                    </a>
                </div>';
            }
          }

          $attachmentsHtml .= '</div></td></tr>';
        }
      }

      echo '
<div class="table-responsive">
    <table class="table table-striped">
        <tr>
            <th width="30%">Warning ID</th>
            <td>' . $warning->id . '</td>
        </tr>
        <tr>
            <th>Client</th>
            <td><a href="clientssummary.php?userid=' . $warning->user_id . '" target="_blank">' .
        $warning->firstname . ' ' . $warning->lastname . '</a> (' . $warning->email . ')</td>
        </tr>
        <tr>
            <th>Date Issued</th>
            <td>' . $warning->created_at . '</td>
        </tr>
        <tr>
            <th>Severity</th>
            <td><span class="label label-' . $severityClass . '">' . $warning->severity . '</span></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><span class="label label-' . $statusClass . '">' . $statusText . '</span></td>
        </tr>';

      if ($warning->acknowledged) {
        echo '
        <tr>
            <th>Acknowledged Date</th>
            <td>' . $warning->acknowledged_at . '</td>
        </tr>';
      }

      if (!empty($warning->expiration_date)) {
        echo '
        <tr>
            <th>Expiration Date</th>
            <td>' . $warning->expiration_date . '</td>
        </tr>';
      }

      echo '
        <tr>
            <th>Created By</th>
            <td>' . htmlspecialchars($warning->created_by) . '</td>
        </tr>
        <tr>
            <th>Warning Message</th>
            <td>' . nl2br(htmlspecialchars($warning->warning_message)) . '</td>
        </tr>';

      if (!empty($warning->details)) {
        echo '
        <tr>
            <th>Additional Details</th>
            <td>' . nl2br(htmlspecialchars($warning->details)) . '</td>
        </tr>';
      }

      echo $attachmentsHtml;

      echo '
    </table>
</div>
<div class="text-right">';

      if (!$warning->archived) {
        if (!$warning->acknowledged) {
          echo '<a href="' . (isset($modulelink) ? $modulelink : '') . '&tab=list&action=acknowledge&id=' . $warning->id .
            '" class="btn btn-success btn-sm">Mark as Acknowledged</a> ';
        }

        echo '<a href="' . (isset($modulelink) ? $modulelink : '') . '&tab=list&action=archive&id=' . $warning->id .
          '" class="btn btn-default btn-sm" onclick="return confirm(\'Are you sure you want to archive this warning?\');">Archive</a> ';
      } else {
        echo '<a href="' . (isset($modulelink) ? $modulelink : '') . '&tab=list&action=unarchive&id=' . $warning->id .
          '" class="btn btn-default btn-sm">Unarchive</a> ';
      }

      echo '<a href="' . (isset($modulelink) ? $modulelink : '') . '&tab=list&action=delete&id=' . $warning->id .
        '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this warning? This action cannot be undone.\');">Delete</a>';

      echo '
</div>';
    } else {
      echo '<div class="alert alert-danger">Warning not found.</div>';
    }
  } else {
    echo '<div class="alert alert-danger">Invalid warning ID.</div>';
  }
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'adminfile') {
  if (!defined('ADMINAREA') || !isset($_SESSION['adminid'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
  }

  $warningId = isset($_GET['warning']) ? (int)$_GET['warning'] : 0;
  $fileIndex = isset($_GET['file']) ? (int)$_GET['file'] : 0;

  if ($warningId > 0) {
    $warning = Capsule::table('mod_clientwarnings')
      ->where('id', $warningId)
      ->first();

    if ($warning && !empty($warning->attachments)) {
      $attachments = json_decode($warning->attachments, true);

      if (is_array($attachments) && isset($attachments[$fileIndex])) {
        $attachment = $attachments[$fileIndex];
        $filePath = dirname(dirname(__FILE__)) . '/' . $attachment['path'];

        if (file_exists($filePath)) {
          header('Content-Type: ' . $attachment['type']);
          header('Content-Disposition: inline; filename="' . basename($attachment['name']) . '"');
          header('Content-Length: ' . filesize($filePath));
          readfile($filePath);
          exit;
        }
      }
    }
  }

  header('HTTP/1.0 404 Not Found');
  exit('File not found');
}

if (isset($_GET['action']) && $_GET['action'] === 'searchClients') {
  if (!defined('ADMINAREA') || !isset($_SESSION['adminid'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
  }

  $searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';

  if (empty($searchTerm)) {
    echo json_encode(array());
    exit;
  }

  $clients = array();

  // Modern WHMCS database query (compatible with newer PHP versions)
  $result = Capsule::table('tblclients')
    ->select('id', 'firstname', 'lastname', 'companyname', 'email')
    ->whereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", ["%{$searchTerm}%"])
    ->orWhere('companyname', 'like', "%{$searchTerm}%")
    ->orWhere('email', 'like', "%{$searchTerm}%")
    ->orderBy('firstname')
    ->orderBy('lastname')
    ->limit(10)
    ->get();

  foreach ($result as $data) {
    $clients[] = array(
      'id' => $data->id,
      'firstname' => $data->firstname,
      'lastname' => $data->lastname,
      'companyname' => $data->companyname,
      'email' => $data->email
    );
  }

  header('Content-Type: application/json');
  echo json_encode($clients);
  exit;
}

function clientwarnings_config()
{
  return [
    "name"        => "Client Warnings",
    "description" => "Issue warnings to clients that require acknowledgment",
    "version"     => "1.1.0",
    "author"      => "<a href='https://26bz.online'>26BZ</a>",
    "fields"      => [
      "enforceAcknowledgment" => [
        "FriendlyName" => "Enforce Warning Acknowledgment",
        "Type"         => "yesno",
        "Description"  => "Require clients to acknowledge warnings",
        "Default"      => "on"
      ],
      "severityLevels" => [
        "FriendlyName" => "Warning Severity Levels",
        "Type"         => "text",
        "Description"  => "Comma-separated list of severity levels",
        "Default"      => "Minor,Major,Critical"
      ],
      "defaultExpirationDays" => [
        "FriendlyName" => "Default Warning Expiration (Days)",
        "Type"         => "text",
        "Description"  => "Number of days after which warnings are automatically archived (0 = never)",
        "Default"      => "90"
      ]
    ]
  ];
}

function clientwarnings_activate()
{
  try {
    if (!Capsule::schema()->hasTable('mod_clientwarnings')) {
      Capsule::schema()->create('mod_clientwarnings', function ($table) {
        $table->increments('id');
        $table->integer('user_id')->unsigned();
        $table->text('warning_message');
        $table->text('details')->nullable();
        $table->text('attachments')->nullable(); // Store attachments as JSON
        $table->string('severity', 20)->default('Major');
        $table->dateTime('created_at');
        $table->tinyInteger('acknowledged')->default(0);
        $table->dateTime('acknowledged_at')->nullable();
        $table->string('created_by', 100)->nullable();
        $table->dateTime('expiration_date')->nullable();
        $table->tinyInteger('archived')->default(0);

        $table->index('user_id');
        $table->index(['user_id', 'acknowledged']);
        $table->index(['user_id', 'archived']);
      });
    } else {
      if (!Capsule::schema()->hasColumn('mod_clientwarnings', 'archived')) {
        Capsule::schema()->table('mod_clientwarnings', function ($table) {
          $table->tinyInteger('archived')->default(0);
        });
      }

      if (!Capsule::schema()->hasColumn('mod_clientwarnings', 'expiration_date')) {
        Capsule::schema()->table('mod_clientwarnings', function ($table) {
          $table->dateTime('expiration_date')->nullable();
        });
      }

      if (!Capsule::schema()->hasColumn('mod_clientwarnings', 'attachments')) {
        Capsule::schema()->table('mod_clientwarnings', function ($table) {
          $table->text('attachments')->nullable();
        });
      }
    }

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
      if (mkdir($uploadDir, 0755, true)) {
        $htaccess = $uploadDir . '.htaccess';
        if (!file_exists($htaccess)) {
          file_put_contents($htaccess, "Require all denied\nOrder deny,allow\nDeny from all\n");
        }
        $index = $uploadDir . 'index.html';
        if (!file_exists($index)) {
          file_put_contents($index, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1><p>You don't have permission to access this resource.</p></body></html>");
        }
      }
    }

    return [
      'status'      => 'success',
      'description' => 'Module activated successfully.'
    ];
  } catch (Exception $e) {
    return [
      'status'      => 'error',
      'description' => 'Error activating module: ' . $e->getMessage()
    ];
  }
}

function clientwarnings_process_uploads()
{
  $validAttachments = array();

  if (!isset($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
    return $validAttachments;
  }

  $uploadDir = __DIR__ . '/uploads/';
  $uploadPath = 'modules/addons/clientwarnings/uploads/';

  if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
      return $validAttachments;
    }
  }

  $allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
  $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
  $maxFileSize = 5 * 1024 * 1024;

  $files = $_FILES['attachments'];
  $count = count($files['name']);

  for ($i = 0; $i < $count; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
      continue;
    }

    $tmpName = $files['tmp_name'][$i];
    $fileName = $files['name'][$i];
    $fileSize = $files['size'][$i];
    $fileType = $files['type'][$i];

    if (!is_uploaded_file($tmpName)) {
      continue;
    }

    if ($fileSize > $maxFileSize || $fileSize <= 0) {
      continue;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);

    if (!in_array($detectedType, $allowedTypes)) {
      continue;
    }

    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
      continue;
    }

    $newFileName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = $uploadDir . $newFileName;

    if (move_uploaded_file($tmpName, $destination)) {
      chmod($destination, 0644);
      $validAttachments[] = array(
        'name' => $fileName,
        'path' => $uploadPath . $newFileName,
        'type' => $detectedType,
        'size' => $fileSize
      );
    }
  }

  return $validAttachments;
}

function clientwarnings_serve_attachment()
{
  if (!isset($_GET['file'], $_GET['warning'])) {
    header('HTTP/1.0 400 Bad Request');
    exit('Invalid request');
  }

  if (!isset($_SESSION['uid']) || empty($_SESSION['uid'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
  }

  $userId = (int)$_SESSION['uid'];
  $warningId = (int)$_GET['warning'];
  $fileIndex = (int)$_GET['file'];

  $warning = Capsule::table('mod_clientwarnings')
    ->where('id', $warningId)
    ->where('user_id', $userId)
    ->first();

  if (!$warning || empty($warning->attachments)) {
    header('HTTP/1.0 404 Not Found');
    exit('Attachment not found');
  }

  $attachments = json_decode($warning->attachments, true);

  if (!is_array($attachments) || !isset($attachments[$fileIndex])) {
    header('HTTP/1.0 404 Not Found');
    exit('Attachment not found');
  }

  $attachment = $attachments[$fileIndex];
  $filePath = dirname(dirname(__FILE__)) . '/' . $attachment['path'];

  if (!file_exists($filePath)) {
    header('HTTP/1.0 404 Not Found');
    exit('File not found');
  }

  header('Content-Type: ' . $attachment['type']);
  header('Content-Disposition: inline; filename="' . basename($attachment['name']) . '"');
  header('Content-Length: ' . filesize($filePath));
  readfile($filePath);
  exit;
}

function clientwarnings_clientarea($vars)
{
  if (isset($_GET['action']) && $_GET['action'] === 'download') {
    clientwarnings_serve_client_attachment();
    exit;
  }

  $userId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
  if ($userId <= 0) {
    return array(
      'pagetitle' => 'Account Warnings',
      'breadcrumb' => array('index.php?m=clientwarnings' => 'Account Warnings'),
      'requirelogin' => true
    );
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_warning'], $_POST['warning_id'])) {
    $warningId = (int)$_POST['warning_id'];
    Capsule::table('mod_clientwarnings')
      ->where('id', $warningId)
      ->where('user_id', $userId)
      ->update(array(
        'acknowledged' => 1,
        'acknowledged_at' => date('Y-m-d H:i:s')
      ));
    header('Location: index.php?m=clientwarnings&success=1');
    exit;
  }

  $warnings = Capsule::table('mod_clientwarnings')
    ->where('user_id', $userId)
    ->where('acknowledged', 0)
    ->where('archived', 0)
    ->whereRaw('(expiration_date IS NULL OR expiration_date > NOW())')
    ->orderBy('created_at', 'desc')
    ->get();

  $formattedWarnings = array();
  foreach ($warnings as $warning) {
    $attachments = array();
    if (!empty($warning->attachments)) {
      $attachments = json_decode($warning->attachments, true) ?: array();
    }

    $formattedWarnings[] = array(
      'id' => $warning->id,
      'message' => $warning->warning_message,
      'details' => $warning->details,
      'attachments' => $attachments,
      'severity' => $warning->severity,
      'date' => $warning->created_at,
      'created_by' => $warning->created_by
    );
  }

  $success = isset($_GET['success']) ? 'Warning has been acknowledged.' : '';

  return array(
    'pagetitle' => 'Account Warnings',
    'breadcrumb' => array('index.php?m=clientwarnings' => 'Account Warnings'),
    'templatefile' => 'clientwarnings',
    'requirelogin' => true,
    'vars' => array(
      'warnings' => $formattedWarnings,
      'success' => $success
    ),
  );
}

function clientwarnings_serve_client_attachment()
{
  $userId = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
  if ($userId <= 0) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
  }

  $warningId = isset($_GET['warning']) ? (int)$_GET['warning'] : 0;
  $fileIndex = isset($_GET['file']) ? (int)$_GET['file'] : 0;

  if ($warningId > 0) {
    $warning = Capsule::table('mod_clientwarnings')
      ->where('id', $warningId)
      ->where('user_id', $userId)
      ->first();

    if ($warning && !empty($warning->attachments)) {
      $attachments = json_decode($warning->attachments, true);

      if (is_array($attachments) && isset($attachments[$fileIndex])) {
        $attachment = $attachments[$fileIndex];
        $filePath = dirname(dirname(__FILE__)) . '/' . $attachment['path'];

        if (file_exists($filePath)) {
          header('Content-Type: ' . $attachment['type']);
          header('Content-Disposition: inline; filename="' . basename($attachment['name']) . '"');
          header('Content-Length: ' . filesize($filePath));
          readfile($filePath);
          exit;
        }
      }
    }
  }

  header('HTTP/1.0 404 Not Found');
  exit('File not found');
}

function clientwarnings_output($vars)
{
  $modulelink = $vars['modulelink'];
  $tab = isset($_GET['tab']) ? $_GET['tab'] : 'add';
  $success = $error = '';

  $severityLevels = explode(',', $vars['severityLevels']);
  $severityLevels = array_map('trim', $severityLevels);

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_id'], $_POST['warning'])) {
    $clientId = (int)$_POST['client_id'];
    $warning = trim($_POST['warning']);
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';
    $severity = isset($_POST['severity']) ? trim($_POST['severity']) : 'Major';
    $expirationDays = isset($_POST['expiration_days']) ? (int)$_POST['expiration_days'] : (int)$vars['defaultExpirationDays'];

    if ($clientId <= 0 || empty($warning)) {
      $error = 'Client ID and warning message are required.';
    } else {
      $adminName = 'System';
      if (isset($_SESSION['adminid'])) {
        $admin = Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->first();
        if ($admin) {
          $adminName = $admin->firstname . ' ' . $admin->lastname;
        }
      }

      $expirationDate = null;
      if ($expirationDays > 0) {
        $expirationDate = date('Y-m-d H:i:s', strtotime("+{$expirationDays} days"));
      }

      $attachments = clientwarnings_process_uploads();
      $attachmentsJSON = !empty($attachments) ? json_encode($attachments) : null;

      Capsule::table('mod_clientwarnings')->insert(array(
        'user_id' => $clientId,
        'warning_message' => $warning,
        'details' => $details,
        'attachments' => $attachmentsJSON,
        'severity' => $severity,
        'created_at' => date('Y-m-d H:i:s'),
        'acknowledged' => 0,
        'created_by' => $adminName,
        'expiration_date' => $expirationDate,
        'archived' => 0
      ));

      $success = 'Warning added successfully.';
    }
  }

  if (isset($_GET['action'], $_GET['id'])) {
    $warningId = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($warningId > 0) {
      if ($action === 'archive') {
        Capsule::table('mod_clientwarnings')
          ->where('id', $warningId)
          ->update(array('archived' => 1));
        $success = 'Warning archived successfully.';
      } elseif ($action === 'unarchive') {
        Capsule::table('mod_clientwarnings')
          ->where('id', $warningId)
          ->update(array('archived' => 0));
        $success = 'Warning unarchived successfully.';
      } elseif ($action === 'delete') {
        $warning = Capsule::table('mod_clientwarnings')
          ->where('id', $warningId)
          ->first();

        if ($warning && !empty($warning->attachments)) {
          $attachments = json_decode($warning->attachments, true);
          if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
              if (isset($attachment['path'])) {
                $filePath = dirname(dirname(__FILE__)) . '/' . $attachment['path'];
                if (file_exists($filePath)) {
                  @unlink($filePath);
                }
              }
            }
          }
        }

        Capsule::table('mod_clientwarnings')
          ->where('id', $warningId)
          ->delete();
        $success = 'Warning deleted successfully.';
      } elseif ($action === 'acknowledge') {
        Capsule::table('mod_clientwarnings')
          ->where('id', $warningId)
          ->update(array(
            'acknowledged' => 1,
            'acknowledged_at' => date('Y-m-d H:i:s')
          ));
        $success = 'Warning marked as acknowledged.';
      }
    }
  }

  echo '
<ul class="nav nav-tabs" role="tablist">
    <li role="presentation" class="' . ($tab == 'add' ? 'active' : '') . '">
        <a href="' . $modulelink . '&tab=add">Add Warning</a>
    </li>
    <li role="presentation" class="' . ($tab == 'list' ? 'active' : '') . '">
        <a href="' . $modulelink . '&tab=list">Manage Warnings</a>
    </li>
    <li role="presentation" class="' . ($tab == 'stats' ? 'active' : '') . '">
        <a href="' . $modulelink . '&tab=stats">Statistics</a>
    </li>
</ul>

<div class="tab-content">
';
  if ($success) {
    echo '<div class="alert alert-success" style="margin-top:10px;">' . $success . '</div>';
  }
  if ($error) {
    echo '<div class="alert alert-danger" style="margin-top:10px;">' . $error . '</div>';
  }

  if ($tab == 'add') {
    echo '
    <div class="tab-pane active">
        <div class="panel panel-default" style="margin-top: 20px;">
            <div class="panel-heading"><h3 class="panel-title">Add Client Warning</h3></div>
            <div class="panel-body">
                <form method="post" action="' . $modulelink . '&tab=add" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="client_id">Select Client</label>
                        <select name="client_id" id="client_id" class="form-control select-client" required>
                            <option value="">-- Select a Client --</option>';
    $clients = Capsule::table('tblclients')
      ->select('id', 'firstname', 'lastname', 'email', 'companyname')
      ->orderBy('firstname')
      ->orderBy('lastname')
      ->limit(1000)
      ->get();

    foreach ($clients as $client) {
      $displayName = $client->firstname . ' ' . $client->lastname;
      if (!empty($client->companyname)) {
        $displayName .= ' (' . $client->companyname . ')';
      }
      $displayName .= ' - ' . $client->email;
      echo '<option value="' . $client->id . '">' . htmlspecialchars($displayName) . '</option>';
    }

    echo '
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="severity">Severity Level</label>
                        <select name="severity" id="severity" class="form-control">';
    foreach ($severityLevels as $level) {
      $selected = ($level == 'Major') ? ' selected' : '';
      echo '<option value="' . $level . '"' . $selected . '>' . $level . '</option>';
    }
    echo '
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="warning">Warning Message</label>
                        <textarea name="warning" id="warning" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="details">Additional Details (Optional)</label>
                        <textarea name="details" id="details" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="attachments">Evidence/Proof (Optional)</label>
                        <input type="file" name="attachments[]" id="attachments" class="form-control" multiple>
                        <small class="text-muted">Accepted file types: JPG, PNG, GIF, PDF (Max 5MB per file)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="expiration_days">Expiration (Days)</label>
                        <input type="number" name="expiration_days" id="expiration_days" class="form-control" value="' . $vars['defaultExpirationDays'] . '" min="0">
                        <small class="text-muted">Number of days until warning expires (0 = never)</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Warning</button>
                </form>
            </div>
        </div>
    </div>';
  } elseif ($tab == 'list') {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $showArchived = isset($_GET['archived']) && $_GET['archived'] == '1';

    echo '
    <div class="tab-pane active">
        <div class="panel panel-default" style="margin-top: 20px;">
            <div class="panel-heading"><h3 class="panel-title">Manage Warnings</h3></div>
            <div class="panel-body">
                <form method="get" action="' . $modulelink . '" class="form" style="margin-bottom: 20px;">
                    <input type="hidden" name="module" value="clientwarnings">
                    <input type="hidden" name="tab" value="list">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Client</label>
                                <select name="client_id" id="manage_client_id" class="form-control select-client">
                                    <option value="">-- All Clients --</option>';
    $clients = Capsule::table('tblclients')
      ->select('id', 'firstname', 'lastname', 'email', 'companyname')
      ->orderBy('firstname')
      ->orderBy('lastname')
      ->limit(1000)
      ->get();

    foreach ($clients as $client) {
      $displayName = $client->firstname . ' ' . $client->lastname;
      if (!empty($client->companyname)) {
        $displayName .= ' (' . $client->companyname . ')';
      }
      $displayName .= ' - ' . $client->email;
      $selected = (isset($_GET['client_id']) && $_GET['client_id'] == $client->id) ? ' selected' : '';
      echo '<option value="' . $client->id . '"' . $selected . '>' . htmlspecialchars($displayName) . '</option>';
    }

    echo '
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warning Content</label>
                                <input type="text" name="search" class="form-control" placeholder="Search warning message content" value="' . htmlspecialchars($search) . '">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><input type="checkbox" name="archived" value="1" ' . ($showArchived ? 'checked' : '') . '> Include Archived Warnings</label>
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="' . $modulelink . '&tab=list" class="btn btn-default">Clear Filters</a>
                        </div>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Warning</th>
                                <th>Severity</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

    $query = Capsule::table('mod_clientwarnings')
      ->join('tblclients', 'mod_clientwarnings.user_id', '=', 'tblclients.id')
      ->select(
        'mod_clientwarnings.*',
        'tblclients.firstname',
        'tblclients.lastname',
        'tblclients.email'
      );

    if (!$showArchived) {
      $query->where('mod_clientwarnings.archived', 0);
    }

    if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
      $query->where('tblclients.id', (int)$_GET['client_id']);
    }

    if (!empty($search)) {
      $query->where('mod_clientwarnings.warning_message', 'like', "%{$search}%");
    }

    $total = $query->count();

    $warnings = $query->orderBy('mod_clientwarnings.created_at', 'desc')
      ->skip($offset)
      ->take($perPage)
      ->get();

    if (count($warnings) > 0) {
      foreach ($warnings as $warning) {
        $statusClass = '';
        $statusText = '';

        if ($warning->archived) {
          $statusClass = 'default';
          $statusText = 'Archived';
        } elseif ($warning->acknowledged) {
          $statusClass = 'success';
          $statusText = 'Acknowledged';
        } else {
          $statusClass = 'warning';
          $statusText = 'Pending';
        }

        if (!empty($warning->expiration_date) && strtotime($warning->expiration_date) < time()) {
          $statusClass = 'default';
          $statusText = 'Expired';
        }

        $severityClass = '';
        switch (strtolower($warning->severity)) {
          case 'minor':
            $severityClass = 'info';
            break;
          case 'major':
            $severityClass = 'warning';
            break;
          case 'critical':
            $severityClass = 'danger';
            break;
          default:
            $severityClass = 'default';
        }

        $hasAttachments = !empty($warning->attachments);
        $messagePreview = htmlspecialchars(substr($warning->warning_message, 0, 50) .
          (strlen($warning->warning_message) > 50 ? '...' : ''));

        echo '
                            <tr>
                                <td>' . $warning->id . '</td>
                                <td><a href="clientssummary.php?userid=' . $warning->user_id . '" target="_blank">' .
          $warning->firstname . ' ' . $warning->lastname . '</a></td>
                                <td>' . $messagePreview . ($hasAttachments ? ' <i class="fas fa-paperclip" title="Has attachments"></i>' : '') . '</td>
                                <td><span class="label label-' . $severityClass . '">' . $warning->severity . '</span></td>
                                <td>' . $warning->created_at . '</td>
                                <td><span class="label label-' . $statusClass . '">' . $statusText . '</span></td>
                                <td class="text-nowrap">';

        echo '<a href="#" class="btn btn-xs btn-info view-warning" data-id="' . $warning->id .
          '" data-toggle="modal" data-target="#warningModal">View</a> ';

        if (!$warning->archived) {
          if (!$warning->acknowledged) {
            echo '<a href="' . $modulelink . '&tab=list&action=acknowledge&id=' . $warning->id .
              '" class="btn btn-xs btn-success">Acknowledge</a> ';
          }

          echo '<a href="' . $modulelink . '&tab=list&action=archive&id=' . $warning->id .
            '" class="btn btn-xs btn-default" onclick="return confirm(\'Are you sure you want to archive this warning?\');">Archive</a> ';
        } else {
          echo '<a href="' . $modulelink . '&tab=list&action=unarchive&id=' . $warning->id .
            '" class="btn btn-xs btn-default">Unarchive</a> ';
        }

        echo '<a href="' . $modulelink . '&tab=list&action=delete&id=' . $warning->id .
          '" class="btn btn-xs btn-danger" onclick="return confirm(\'Are you sure you want to delete this warning? This action cannot be undone.\');">Delete</a>';

        echo '
                                </td>
                            </tr>';
      }
    } else {
      echo '
                            <tr>
                                <td colspan="7" class="text-center">No warnings found matching your criteria.</td>
                            </tr>';
    }

    echo '
                        </tbody>
                    </table>
                </div>';

    $totalPages = ceil($total / $perPage);
    if ($totalPages > 1) {
      echo '
                <ul class="pagination">
                    <li class="' . ($page <= 1 ? 'disabled' : '') . '">
                        <a href="' . ($page <= 1 ? '#' : $modulelink . '&tab=list&page=' . ($page - 1) . '&search=' . urlencode($search) . '&archived=' . ($showArchived ? '1' : '0')) . '">&laquo;</a>
                    </li>';

      for ($i = max(1, $page - 2); $i <= min($page + 2, $totalPages); $i++) {
        echo '
                    <li class="' . ($i == $page ? 'active' : '') . '">
                        <a href="' . $modulelink . '&tab=list&page=' . $i . '&search=' . urlencode($search) . '&archived=' . ($showArchived ? '1' : '0') . '">' . $i . '</a>
                    </li>';
      }

      echo '
                    <li class="' . ($page >= $totalPages ? 'disabled' : '') . '">
                        <a href="' . ($page >= $totalPages ? '#' : $modulelink . '&tab=list&page=' . ($page + 1) . '&search=' . urlencode($search) . '&archived=' . ($showArchived ? '1' : '0')) . '">&raquo;</a>
                    </li>
                </ul>';
    }

    echo '
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="warningModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Warning Details</h4>
                </div>
                <div class="modal-body">
                    <div id="warningDetails">Loading...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $(".view-warning").click(function() {
            var warningId = $(this).data("id");
            $("#warningDetails").html("<p class=\'text-center\'><i class=\'fa fa-spinner fa-spin\'></i> Loading warning details...</p>");
            
            $.ajax({
                url: "' . $modulelink . '&action=getWarningDetails&id=" + warningId,
                type: "GET",
                success: function(data) {
                    $("#warningDetails").html(data);
                },
                error: function() {
                    $("#warningDetails").html("<div class=\'alert alert-danger\'>Error loading warning details. Please try again.</div>");
                }
            });
        });
    });
    </script>';
  } elseif ($tab == 'stats') {
    echo '
    <div class="tab-pane active">
        <div class="panel panel-default" style="margin-top: 20px;">
            <div class="panel-heading"><h3 class="panel-title">Warning Statistics</h3></div>
            <div class="panel-body">';

    $totalWarnings = Capsule::table('mod_clientwarnings')->count();
    $pendingWarnings = Capsule::table('mod_clientwarnings')
      ->where('acknowledged', 0)
      ->where('archived', 0)
      ->count();
    $archivedWarnings = Capsule::table('mod_clientwarnings')
      ->where('archived', 1)
      ->count();

    $warningsBySeverity = Capsule::table('mod_clientwarnings')
      ->select('severity', Capsule::raw('COUNT(*) as count'))
      ->groupBy('severity')
      ->get();

    $currentMonth = date('n');
    $currentYear = date('Y');
    $monthlyData = array();

    for ($i = 0; $i < 6; $i++) {
      $month = ($currentMonth - $i) > 0 ? ($currentMonth - $i) : (12 + ($currentMonth - $i));
      $year = ($currentMonth - $i) > 0 ? $currentYear : ($currentYear - 1);
      $startDate = sprintf('%d-%02d-01 00:00:00', $year, $month);
      $endDate = date('Y-m-t 23:59:59', strtotime($startDate));

      $count = Capsule::table('mod_clientwarnings')
        ->whereBetween('created_at', array($startDate, $endDate))
        ->count();

      $monthName = date('F Y', strtotime($startDate));
      $monthlyData[$monthName] = $count;
    }

    $topClients = Capsule::table('mod_clientwarnings')
      ->join('tblclients', 'mod_clientwarnings.user_id', '=', 'tblclients.id')
      ->select(
        'mod_clientwarnings.user_id',
        'tblclients.firstname',
        'tblclients.lastname',
        'tblclients.email',
        Capsule::raw('COUNT(*) as warning_count')
      )
      ->groupBy('mod_clientwarnings.user_id')
      ->orderBy('warning_count', 'desc')
      ->limit(5)
      ->get();

    echo '
            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">Summary</div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-xs-8">Total Warnings</div>
                                <div class="col-xs-4 text-right"><strong>' . number_format($totalWarnings) . '</strong></div>
                            </div>
                            <div class="row">
                                <div class="col-xs-8">Pending Acknowledgment</div>
                                <div class="col-xs-4 text-right"><strong>' . number_format($pendingWarnings) . '</strong></div>
                            </div>
                            <div class="row">
                                <div class="col-xs-8">Archived Warnings</div>
                                <div class="col-xs-4 text-right"><strong>' . number_format($archivedWarnings) . '</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">By Severity</div>
                        <div class="panel-body">';
    foreach ($warningsBySeverity as $severity) {
      echo '
                            <div class="row">
                                <div class="col-xs-8">' . htmlspecialchars($severity->severity) . '</div>
                                <div class="col-xs-4 text-right"><strong>' . number_format($severity->count) . '</strong></div>
                            </div>';
    }
    echo '
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">Top Warned Clients</div>
                        <div class="panel-body">';
    if (count($topClients) > 0) {
      foreach ($topClients as $client) {
        echo '
                            <div class="row">
                                <div class="col-xs-8">
                                    <a href="clientssummary.php?userid=' . $client->user_id . '">' .
          $client->firstname . ' ' . $client->lastname . '</a>
                                </div>
                                <div class="col-xs-4 text-right"><strong>' . $client->warning_count . '</strong></div>
                            </div>';
      }
    } else {
      echo '<p>No client warning data available.</p>';
    }
    echo '
                        </div>
                    </div>
                </div>
            </div>';

    echo '
            <div class="panel panel-default">
                <div class="panel-heading">Monthly Warnings (Last 6 Months)</div>
                <div class="panel-body">
                    <div id="monthlyChart" style="height: 300px;"></div>
                </div>
            </div>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var ctx = document.createElement("canvas");
                    document.getElementById("monthlyChart").appendChild(ctx);
                    
                    var labels = [];
                    var data = [];
                    
                    ';
    $monthlyData = array_reverse($monthlyData);
    foreach ($monthlyData as $month => $count) {
      echo 'labels.push("' . $month . '");';
      echo 'data.push(' . $count . ');';
    }
    echo '
                    var myChart = new Chart(ctx, {
                        type: "bar",
                        data: {
                            labels: labels,
                            datasets: [{
                                label: "Warnings Issued",
                                data: data,
                                backgroundColor: "rgba(54, 162, 235, 0.6)",
                                borderColor: "rgba(54, 162, 235, 1)",
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                yAxes: [{
                                    ticks: {
                                        beginAtZero: true,
                                        callback: function(value) { if (value % 1 === 0) { return value; } }
                                    }
                                }]
                            }
                        }
                    });
                });
            </script>
        </div>
    </div>
</div>';
  }

  echo '</div>';

  echo '<script type="text/javascript">
    $(document).ready(function() {
        $(".select-client").select2({
            placeholder: "Search for a client...",
            allowClear: true,
            width: "100%"
        });
    });
</script>';
}
