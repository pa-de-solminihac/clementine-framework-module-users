<?php
$current_url = $request->EQUIV[$request->LANG];
?>
<div class="form_users_login">
<h1>
<?php
if (isset($data['message'])) {
    echo $data['message'];
}
?>
</h1>
<form action="<?php echo __WWW__; ?>/users/login?url_retour=<?php echo (isset($data['url_retour'])) ? urlencode($data['url_retour']) : urlencode($current_url); ?>" method="post" >
    <p>
        <label>Adresse e-mail</label><input type="text" id="form_users_login" name="login" value="" tabindex="1" />
        <label>Mot de passe</label><input type="password" name="password" value="" tabindex="2" />
        <label>&nbsp;</label><input type="submit" value="Connexion" />
        <a href="<?php echo __WWW__; ?>/users/oubli">Mot de passe oublié</a>
    </p>
</form>
<script type="text/javascript">
    document.getElementById('form_users_login').focus();
</script>
</div>
