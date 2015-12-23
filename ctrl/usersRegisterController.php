<?php
/**
 * usersRegisterController : page d'inscription frontend
 *
 * @package
 * @version $id$
 * @copyright
 * @author Pierre-Alexis <pa@quai13.com>
 * @license
 */
class usersRegisterController extends usersRegisterController_Parent
{

    // cette page permet de se créer un compte utilisateur, elle n'est accessible que si le paramètre [module_users]allow_frontend_register est activé
    public function indexAction($request, $params = null)
    {
        $conf = Clementine::getModuleConfig('register');
        if (!$conf['allow_frontend_register']) {
            return $this->trigger404();
        }
        $users = Clementine::getModel('register');
        if ($auth = $users->getAuth()) {
            Clementine::getModel('fonctions')->redirect(__WWW__);
        }
        $params['skip_auth'] = 1;
        $userscontroller = $this->getController('register');
        $ret = $userscontroller->createAction($request, $params);
        $userscontroller->removeClass('more_classes_wrap', 'well');
        $this->data = $userscontroller->data;
        return $ret;
    }

}
