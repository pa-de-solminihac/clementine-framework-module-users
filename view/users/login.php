<?php
$this->getBlock('design/header', $data, $request);
$current_url = $request->EQUIV[$request->LANG];
?>
<div class="container form_users_login">
    <div class="row">
        <div class="col-md-4 col-md-offset-4">
            <div class="login-panel panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        Connexion à votre compte
                    </h3>
                </div>
                <div class="panel-body">
<?php
if (!empty($data['message'])) {
?>
                    <div class="error alert alert-danger">
<?php
    echo $data['message'];
?>
                    </div>
<?php
}
?>
                    <form role="form" action="<?php echo __WWW__; ?>/users/login?url_retour=<?php echo (isset($data['url_retour'])) ? urlencode($data['url_retour']) : urlencode($current_url); ?>" method="post">
                        <fieldset>
                            <div class="form-group">
                                <input class="form-control" placeholder="Adresse e-mail" id="form_users_login" name="login" type="text" autofocus tabindex="1">
                            </div>
                            <div class="form-group">
                                <input class="form-control" placeholder="Mot de passe" name="password" type="password" value="" tabindex="2">
                            </div>
                            <div class="form-group">
                                <a href="<?php echo __WWW__; ?>/users/oubli">Mot de passe oublié ?</a>
                            </div>
                            <input type="submit" class="btn btn-lg btn-success btn-block" value="Connexion" />
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$this->getBlock('design/footer', $data, $request);
