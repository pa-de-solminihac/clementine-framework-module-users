<?php
Clementine::getBlock('design/header', $data, $request);
?>
<div class="container form_users_logout">
    <div class="row">
        <div class="col-md-4 col-md-offset-4">
            <div class="login-panel panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        Connexion à votre compte
                    </h3>
                </div>
                <div class="panel-body">
                    <p class="info alert alert-success">
                        Vous avez bien été déconnecté.
                    </p>
                    <p>
                        <a href="<?php echo __WWW__; ?>/">
                            <i class="glyphicon glyphicon-arrow-left"></i>
                            revenir à l'accueil
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
Clementine::getBlock('design/footer', $data, $request);
