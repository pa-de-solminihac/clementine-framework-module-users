<div class="form_users_oubli">
    <form action="<?php echo __WWW__; ?>/users/oubli" method="post">
<?php
if (isset($data['error'])) {
?>
        <div class="error">
<?php
    echo $data['error'];
?>
        </div>
        <br />
<?php
}
if (isset($data['message'])) {
?>
        <div class="message">
<?php
    echo $data['message'];
?>
        </div>
        <br />
<?php
} else {
?>
        <div>
            <br /><strong>Veuillez remplir le formulaire ci-dessous. </strong><br />
        </div>
        <div class="spacer"></div>
        <label>Votre adresse e-mail</label>
        <input type="text" id="login" name="login" value="" />
        <input type="hidden" id="url_retour" name="url_retour" value="<?php echo (isset($data['url_retour'])) ? $data['url_retour'] : __WWW__; ?>" />
        <label>&nbsp;</label><input type="submit" value="Renouveler" />
        <div class="spacer"></div>
<?php
}
?>
    </form>
</div>
