<style>

.rapidmailwidget-button {
    display: flex;
    justify-content: right;
}

#widget-RapidmailWidget .widget-title:before {
    content: "\f0e0";
}

.not-configured {
    color: #ff000099;
}

</style>
<div class="">
    <div class="widget-custom-data">
        <table class="table table-condensed table-hover table-blank">
            <tbody>
                <tr>
                    <td>Zugangsdaten Rapidmail</td>
                    <td class="text-right">
                        <span class="label label-success"><i class="fal {if $login_valid}fa-check text-success{else}fa-times not-configured{/if}" aria-hidden="true"></i></span>
                    </td>
                </tr>
                <tr>
                    <td>Empfängerlisten verbunden</td>
                    <td class="text-right">
                        <span class="label label-success"><i class="fal {if $lists_valid}fa-check text-success{else}fa-times not-configured{/if}" aria-hidden="true"></i></span>
                    </td>
                </tr>
                <tr>
                    <td>Automatische Anmeldung Aktiv</td>
                    <td class="text-right">
                        <span class="label label-success"><i class="fal {if $reg_valid}fa-check text-success{else}fa-times not-configured{/if}" aria-hidden="true"></i></span>
                    </td>
                </tr>
                <tr>
                    <td>Automatische Abmeldung Aktiv</td>
                    <td class="text-right">
                        <span class="label label-success"><i class="fal {if $dereg_valid}fa-check text-success{else}fa-times not-configured{/if}" aria-hidden="true"></i></span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="rapidmailwidget-button">
        <a href="{$plugin_path}"><button type="button" class="btn btn-primary" style="align-self: right;">Zum Plugin</button></a>
    <div>
</div>
