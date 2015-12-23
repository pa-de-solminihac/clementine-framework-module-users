<?php
Clementine::getBlock('design/header', $data, $request);
?>
<div class="container form_users_logout">
    <div class="row">
        <div class="col-md-4 col-md-offset-4">
            <div class="login-panel panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        Votre inscription s'est bien déroulée.
                    </h3>
                </div>
                <div class="panel-body">
<?php
if ($auth = Clementine::getModel('users')->getAuth()) {
?>
                    <p>
                        Vous êtes actuellement connecté.
                    </p>
<?php
} else {
?>
                    <a href="<?php echo __WWW__ . '/users/login'; ?>" class="pull-right">
                        page de connexion
                        <i class="glyphicon glyphicon-arrow-right"></i>
                    </a>
<?php
}
?>
                    <a href="<?php echo __WWW__; ?>/">
                        <i class="glyphicon glyphicon-arrow-left"></i>
                        revenir à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
Clementine::getBlock('design/footer', $data, $request);
