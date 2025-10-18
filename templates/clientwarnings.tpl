{if $success}
    <div class="alert alert-success">
        {$success}
    </div>
{/if}

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">Account Warnings</h3>
    </div>
    <div class="panel-body">
        {if count($warnings) == 0}
            <p>You have no active warnings on your account.</p>
        {else}
            {foreach from=$warnings item=warning}
                <div class="panel panel-warning client-warning">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <span class="label label-danger">Action Required</span>
                            Warning issued on {$warning.date|date_format:"%B %d, %Y"}
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div class="warning-message">
                            {$warning.message|nl2br}
                        </div>
                        
                        {if $warning.details}
                            <hr>
                            <h5>Additional Details:</h5>
                            <div class="warning-details">
                                {$warning.details|nl2br}
                            </div>
                        {/if}
                        
                        {if $warning.attachments && count($warning.attachments) > 0}
                            <hr>
                            <h5>Evidence/Proof:</h5>
                            <div class="warning-attachments">
                                <div class="row">
                                    {foreach from=$warning.attachments key=index item=attachment}
                                        <div class="col-md-4 col-sm-6 margin-bottom-10">
                                            {*
                                                Check if the attachment type contains "image/"
                                                (using the strstr modifier to detect image files)
                                            *}
                                            {if $attachment.type|strstr:"image/"}
                                                <div class="thumbnail">
                                                    <a href="index.php?m=clientwarnings&action=download&warning={$warning.id}&file={$index}" target="_blank">
                                                        <img src="index.php?m=clientwarnings&action=download&warning={$warning.id}&file={$index}" alt="{$attachment.name}" class="img-responsive" style="max-height: 150px;">
                                                    </a>
                                                    <div class="caption text-center">
                                                        <small>{$attachment.name}</small>
                                                    </div>
                                                </div>
                                            {else}
                                                <a href="index.php?m=clientwarnings&action=download&warning={$warning.id}&file={$index}" target="_blank" class="btn btn-default btn-block">
                                                    <i class="fa fa-download"></i> {$attachment.name}
                                                </a>
                                            {/if}
                                        </div>
                                    {/foreach}
                                </div>
                            </div>
                        {/if}
                        
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Issued by:</strong> {$warning.created_by}</p>
                                <p>
                                    <strong>Severity:</strong> 
                                    <span class="label label-{if $warning.severity eq 'Critical'}danger{elseif $warning.severity eq 'Major'}warning{else}info{/if}">
                                        {$warning.severity}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <form method="post" action="">
                                    <input type="hidden" name="warning_id" value="{$warning.id}" />
                                    <button type="submit" name="acknowledge_warning" class="btn btn-primary">
                                        I Acknowledge This Warning
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            {/foreach}
        {/if}
    </div>
</div>

<style>
    .client-warning {
        margin-bottom: 20px;
    }
    .warning-message {
        font-size: 14px;
        line-height: 1.6;
    }
    .margin-bottom-10 {
        margin-bottom: 10px;
    }
</style>
