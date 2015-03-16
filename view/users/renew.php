<?php
$this->getBlock('design/header', $data, $request);
$current_url = $request->EQUIV[$request->LANG];
?>
<div class="container form_users_renew">
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
                        <p>
                            <a href="<?php echo __WWW__; ?>/">
                                <i class="glyphicon glyphicon-arrow-left"></i>
                                revenir à l'accueil
                            </a>
                        </p>
<?php
} else {
?>
                        <p class="alert alert-info">
                            <strong>Vos identifiants ont bien été renouvelés et vous ont été transmis par e-mail.</strong> 
                            <br />
                            <br />
                            Vous devriez les recevoir dans quelques instants. 
                        </p>
                        <p>
                            <a href="<?php echo __WWW__; ?>/users/login" class="pull-right">
                                formulaire de connexion
                                <i class="glyphicon glyphicon-arrow-right"></i>
                            </a>
                        </p>
<?php
}
?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$this->getBlock('design/footer', $data, $request);
