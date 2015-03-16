<?php
$users = $this->getModel('users');
if ($auth = $this->getModel('users')->getAuth()) {
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
    $conf = $this->getModuleConfig();
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
    $data['navbar-toplinks'] = array_merge($data['navbar-toplinks'], $toplinks);
}
$this->getParentBlock($data);
