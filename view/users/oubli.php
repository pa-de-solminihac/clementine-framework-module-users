<?php
Clementine::getBlock('design/header', $data, $request);
$current_url = $request->EQUIV[$request->LANG];
?>
<div class="container form_users_oubli">
    <div class="row">
        <div class="col-md-4 col-md-offset-4">
            <div class="login-panel panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                    Veuillez remplir le formulaire ci-dessous. 
                    </h3>
                </div>
                <div class="panel-body">
<?php
if (isset($data['error'])) {
?>
        <p class="error alert alert-danger">
<?php
    echo $data['error'];
?>
        </p>
<?php
}
if (isset($data['message'])) {
?>
        <p class="info alert alert-success">
<?php
    echo $data['message'];
?>
        </p>
        <p>
            <a href="<?php echo __WWW__; ?>/">
                <i class="glyphicon glyphicon-arrow-left"></i>
                revenir à l'accueil
            </a>
        </p>
<?php
} else {
?>
                    <form role="form" action="<?php echo __WWW__; ?>/users/oubli" method="post">
                        <fieldset>
                            <div class="form-group">
                                <input class="form-control" placeholder="Adresse e-mail" id="login" name="login" type="text" autofocus tabindex="1">
                            </div>
                            <input type="hidden" id="url_retour" name="url_retour" value="<?php echo (isset($data['url_retour'])) ? $data['url_retour'] : __WWW__; ?>" />
                            <input type="submit" class="btn btn-lg btn-success btn-block" value="Renouveler mon mot de passe" />
                        </fieldset>
                    </form>
<?php
}
?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
Clementine::getBlock('design/footer', $data, $request);
