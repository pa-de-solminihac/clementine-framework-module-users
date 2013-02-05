<?php
/**
 * usersHookHelper 
 * 
 * @package 
 * @version $id$
 * @copyright 
 * @author Pierre-Alexis <pa@quai13.com> 
 * @license 
 */
class usersHookHelper extends usersHookHelper_Parent
{
    /**
     * before_first_getRequest : fonction appelee avant le premier appel a getRequest()
     * 
     * @access public
     * @return void
     */
    function before_first_getRequest()
    {
        // appelle le hook parent s'il existe
        parent::before_first_getRequest();
        // utilisation du hook 'before_first_getRequest'
        $this->getModel('users')->getAuth();
    }

}
?>
