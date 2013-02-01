<div class="form_users_renew">
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
} else {
?>
    <div>
        <strong>Vos identifiants ont bien été renouvelés et vous ont été transmis par e-mail.</strong> 
        <br />
        <br />
        Vous devriez les recevoir dans quelques instants. 
        <br />
        <br />
        <a href="<?php echo __WWW__; ?>/">Retour à l'accueil</a>
        <br />
        <br />
    </div>
<?php
}
?>
</div>
