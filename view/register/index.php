<?php
$data['hidden_sections']['header'] = 1;
$data['hidden_sections']['footer'] = 1;
$data['hidden_sections']['backbutton'] = 1;
$data['hidden_sections']['savebutton'] = 1;
if (!$request->AJAX) {
    $this->getBlock('design/header', $data, $request);
?>
<div class="container form_users_login">
    <div class="row">
        <div class="col-md-4 col-md-offset-4">
            <div class="login-panel panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        Créez votre compte
                    </h3>
                </div>
                <div class="panel-body">
<?php
    $this->getBlock('register/create', $data, $request);
?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $this->getBlock('design/footer', $data, $request);
}
