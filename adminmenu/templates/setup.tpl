<style>
    .main-page{
        display: block;
        justify-content: center;
    }

    .center-box{
        display: flex;
        justify-content: center !important;
    }

    .setup-outer-box{
        width: 40%;
    }


    .progress-slider > li:not(:last-child):after {
        border-top: 30px inset transparent;
        border-bottom: 30px inset transparent;
        border-left: 30px solid #f3f3f3;
        position: absolute;
        content: "";
        z-index: 4;
        right: -30px;
    }

    .progress-slider > li.active:not(:last-child):after {
        border-left: 30px solid #5cbcf6;
    }

    .progress-slider > li:not(:first-child):before {       
        border-top: 30px inset transparent;
        border-bottom: 30px inset transparent;
        border-left: 30px solid #ffffff;
        position: absolute;
        content: "";             
        z-index: 3;
    }

    .darkmode-active-rapidmail .progress-slider > li:not(:first-child):before{
        border-left: 30px solid #435a6b;
    }

    .progress-slider > li > a, .progress-slider > li .nav-badge {
        font-size: 16px;
        display: inline-block;
        overflow: hidden;
        text-align: center;
        color: #777777;
        background-color: #f3f3f3;
        padding-top: 17px;
        padding-bottom: 17px;
        padding-left: 60px;
        padding-right: 30px;
    }

    .progress-slider > li.active > a, .progress-slider > li.active .nav-badge {
        background-color: #5cbcf6;
        color: #fff;
    }

    .progress-slider > li {
        display: inline-block;
        float: left;
        padding: 0;
        position: relative;
        
    }
    .progress-slider > li:not(:first-child) {
        padding-left: 3px;
    }

    .progress-slider > li.set {
        cursor: pointer;
    }

    .panel-body {
        display: flex;
        justify-content: center;
    }

    .check-button{
        margin-top: 15px;
        width: 100%;
    }

</style>

<div class="main-page">

    <div class="panel-body">
        <div class="progress-slider-body">
            <ul class="progress-slider">
                <li data-target="#settingsCarousel" data-slide-to="0" class="set active rounded">
                    <span class="nav-badge" title="Verbindung mit RapidMail">
                        <i class="fa fa-user"></i>
                        <span class="hidden-xs">Zugangsdaten</span>
                    </span>
                </li>
                <li data-target="#settingsCarousel" data-slide-to="1" class="set {if $step >= 2}active{/if}">
                    <span class="nav-badge">
                        <i class="fa fa-list"></i>
                        <span class="hidden-xs">Empfängerlisten</span>

                    </span>
                </li>
                <li data-target="#settingsCarousel" data-slide-to="2" class="set {if $step >= 3}active{/if}">
                    <span class="nav-badge">
                        <i class="fa fa-exchange"></i>
                        <span class="hidden-xs">Datenaustausch</span>
                    </span>
                </li>
                <li data-target="#settingsCarousel" data-slide-to="3" class="set {if $step >= 4}active{/if}">
                    <span class="nav-badge">
                        <i class="fa fa-gear"></i>
                        <span class="hidden-xs">Laufende Einstellungen</span>
                    </span>
                </li>
            </ul>
        </div>
    </div>

<br>
    {if $step == 1}
    <div class="center-box">
        <div class="setup-outer-box">
            <div class="setup-inputs">
                <form method="post">
                    {$jtl_token}
                    <input type="hidden" name="kPluginAdminMenu" value="{$menuID}">
                    <span>Username</span><input class="form-control" name="api_user" value="{$api_user|default:''}">
                    <span>Passwort</span><input class="form-control" name="api_pass" value="{$api_pass|default:''}">
                    {$feedback}
                    <button type="submit" class="btn btn-primary check-button">Zugangsdaten prüfen!</button>
                </form>
            </div>
        </div>
    </div>
    {/if}
    {if $step == 2}
    <div class="center-box">
        <div class="setup-outer-box">
            <div class="setup-inputs">
                <form method="post">
                    {$jtl_token}
                    <span>Empfängerliste Deutsch</span>
                    <select name="list_id_deutsch" class="custom-select">
                        <option disabled>Bitte wählen!</option>
                        {foreach $lists as $list}
                            <option value="{$list['id']}" {if $list['id'] == $listid_de} selected {/if}>{$list['name']}</option>
                        {/foreach}
                    </select>
                    <span>Empfängerliste Englisch</span>
                    <select name="list_id_englisch" class="custom-select">
                        <option disabled>Bitte wählen!</option>
                        {foreach $lists as $list}
                            <option value="{$list['id']}" {if $list['id'] == $listid_en} selected {/if}>{$list['name']}</option>
                        {/foreach}
                    </select>
                    {$feedback}
                    <button type="submit" class="btn btn-primary check-button">Speichern!</button>
                </form>
                </br>
                <form method="post">
                    {$jtl_token}
                    <span>Name für neue Liste</span>
                    <input class="form-control" name="list-name" value="{$list_name|default:''}">
                    {$new_list_feedback}
                    <button type="submit" class="btn btn-primary check-button">Neue Liste Erstellen!</button>
                </form>
            </div>
        </div>
    </div>
    {/if}
    {if $step == 3}
    <div class="center-box">
        <div class="setup-outer-box">
            <div class="setup-inputs">
                <form method="post">
                    {$jtl_token}
                    <span>Datum</span><input type="date" class="form-control" name="date-control" value="{$date_input|default:''}">
                    <button type="submit" class="btn btn-primary check-button">Empfänger exportieren!</button>
                    {$feedback}
                </form>
            </div>
        </div>
    </div>
    {/if}
    {if $step == 4}
    <div class="center-box">
        <div class="setup-outer-box">
            <div class="setup-inputs">
                <form method="post">
                    {$jtl_token}
                    <span>AutoRegistration</span>
                    <select class="custom-select" name="auto-reg">
                        <option value="1" {if $reg_status == 1}selected{/if}>Aktiv</option>
                        <option value="0" {if $reg_status == 0}selected{/if}>Inatkiv</option>
                    </select>
                    <span>AutoDeregistration</span>
                    <select class="custom-select" name="auto-dereg">
                        <option value="1" {if $dereg_status == 1}selected{/if}>Aktiv</option>
                        <option value="0" {if $dereg_status == 0}selected{/if}>Inaktiv</option>
                    </select>
                    {$feedback}
                    <button type="submit" class="btn btn-primary check-button">Speichern!</button>
                </form>
            </div>
        </div>
    </div>
    {/if}

    <div class="save-wrapper">
        <div class="row second-ml-auto">
            <div class="col-sm-6 col-xl-auto text-left">

            </div>
            <div class="col-sm6 col-xl-auto">
                <form method="post">
                    {$jtl_token}
                    <button name="step_back" type="submit" value="step_back" class="btn btn-warning btn-block">
                        <i class="fa fa-arrow-left"></i>
                        Zurück
                    </button>
                </form>
            </div>
            <div class="col-sm6 col-xl-auto">
                <form method="post">
                    {$jtl_token}
                    <input type="hidden" name="kPluginAdminMenu" value="{$menuID}">
                    <input type="hidden" name="step_forward" value="1" class="form-control">
                    <button name="button_forward" type="submit" class="btn btn-primary btn-block"> 
                        Weiter
                        <i class="fa fa-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>