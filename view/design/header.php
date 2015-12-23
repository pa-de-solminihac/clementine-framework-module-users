<?php
$ns = Clementine::getModel('fonctions');
$users = Clementine::getModel('users');
if ($auth = Clementine::getModel('users')->getAuth()) {
    $toplinks = array (
        $auth['login'] => array (
            'url' => '#',
            'icon' => '<i class="glyphicon glyphicon-user"></i>',
            'dropdown' => array(
                'block' => 'users/dropdown',
            ),
        )
    );
} else {
    $toplinks = array();
    //'Mon compte' => array(
    //'url' => '#',
    //'icon' => '<i class="fa fa-user fa-fw"></i>',
    //),
    //'divider',
    $conf = Clementine::getModuleConfig('users');
    if ($conf['allow_frontend_register']) {
        $toplinks['Inscription'] = array(
            'url' => __WWW__ . '/register',
            'icon' => '<i class="glyphicon glyphicon-pencil"></i>',
        );
    }
    $toplinks['Connexion'] = array(
        'url' => $users->getUrlLogin(),
        'icon' => '<i class="glyphicon glyphicon-user"></i>',
    );
}
if (empty($data['navbar-toplinks'])) {
    $data['navbar-toplinks'] = $toplinks;
} else {
    $data['navbar-toplinks'] = $ns->array_override($toplinks, $data['navbar-toplinks']);
}
Clementine::getParentBlock($data);
