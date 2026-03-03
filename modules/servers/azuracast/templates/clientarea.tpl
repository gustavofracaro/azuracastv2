<div class='row'>
    <div class="col">
        <a target="_blank" href="/clientarea.php?action=productdetails&id={$id}&dosinglesignon=1" class="btn btn-info btn-block">
                Login To AzuraCast
        </a>
    </div>
</div>
<hr />
<h4 class="text-left">Package Information:</h4>
<br />
{foreach from=$productConfigOptions key=configName item=configValue}
    <div class="row" style="margin-bottom:3px;">
        <div class="col-sm-5 text-right">
            <strong>{$configName}</strong>
        </div>
        <div class="col-sm-7 text-left">
            {{$configValue}}
        </div>
    </div>
{/foreach}