<div class="form_users_login">
<h1>
<?php
if (isset($data['message'])) {
    echo $data['message'];
}
?>
</h1>
<form action="<?php echo __WWW__; ?>/users/login?url_retour=<?php echo (isset($data['url_retour'])) ? urlencode($data['url_retour']) : urlencode(__WWW__); ?>" method="post" >
    <p>
        <label>Adresse email</label><input type="text" id="form_users_login" name="login" value="" />
        <label>Mot de passe</label><input type="password" name="password" value="" />
        <input type="hidden" id="form_users_url_retour" name="url_retour" value="<?php echo (isset($data['url_retour'])) ? $data['url_retour'] : __WWW__; ?>" />
        <label>&nbsp;</label><input type="submit" value="Connexion" />
        <a href="<?php echo __WWW__; ?>/users/oubli">Mot de passe oublié</a>
    </p>
</form>
<script type="text/javascript">
    document.getElementById('form_users_login').focus();
</script>
</div>
