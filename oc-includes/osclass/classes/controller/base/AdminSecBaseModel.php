<?php

/*
 *  Copyright 2020 Mindstellar Osclass
 *  Maintained and supported by Mindstellar Community
 *  https://github.com/mindstellar/Osclass
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use mindstellar\utility\Utils;

/**
 * Class AdminSecBaseModel
 */
class AdminSecBaseModel extends SecBaseModel
{
    public function __construct()
    {
        parent::__construct();

        // check if is moderator and can enter to this page
        if ($this->isModerator()
            && !in_array($this->page, osc_apply_filter('moderator_access', array(
                'items',
                'comments',
                'media',
                'login',
                'admins',
                'ajax',
                'stats',
                ''
            )), false)
        ) {
            osc_add_flash_error_message(_m("You don't have enough permissions"), 'admin');
            $this->redirectTo(osc_admin_base_url());
        }

        osc_run_hook('init_admin');

        $config_version = OSCLASS_VERSION;

        if (Utils::versionCompare($config_version, osc_get_preference('version'), 'gt')) {
            if ($this instanceof CAdminTools || IS_AJAX) {
            } elseif (!$this instanceof CAdminUpgrade) {
                $this->redirectTo(osc_admin_base_url(true) . '?page=upgrade');
            }
        }

        // show donation successful
        if (Params::getParam('donation') === 'successful') {
            osc_add_flash_ok_message(_m('Thank you very much for your donation'), 'admin');
        }

        // enqueue scripts
        osc_enqueue_script('jquery');
        osc_enqueue_script('jquery-ui');
        osc_enqueue_script('admin-osc');
        osc_enqueue_script('admin-ui-osc');
    }

    /**
     * @return bool
     */
    public function isModerator()
    {
        return osc_is_moderator();
    }

    /**
     * @return bool
     */
    public function isLogged()
    {
        return osc_is_admin_user_logged_in();
    }

    public function logout()
    {
        //destroying session
        $locale = Session::newInstance()->_get('oc_adminLocale');
        Session::newInstance()->session_destroy();
        Session::newInstance()->_drop('adminId');
        Session::newInstance()->_drop('adminUserName');
        Session::newInstance()->_drop('adminName');
        Session::newInstance()->_drop('adminEmail');
        Session::newInstance()->_drop('adminLocale');
        Session::newInstance()->session_start();
        Session::newInstance()->_set('oc_adminLocale', $locale);

        Cookie::newInstance()->pop('oc_adminId');
        Cookie::newInstance()->pop('oc_adminSecret');
        Cookie::newInstance()->pop('oc_adminLocale');
        Cookie::newInstance()->set();
    }

    /**
     * @param $file
     */
    public function doView($file)
    {
        osc_run_hook('before_admin_html');
        osc_current_admin_theme_path($file);
        Session::newInstance()->_clearVariables();
        osc_run_hook('after_admin_html');
    }

    public function showAuthFailPage()
    {
        if (Params::getParam('page') === 'ajax') {
            echo json_encode(array('error' => 1, 'msg' => __('Session timed out')));
            exit;
        }

        Session::newInstance()->_setReferer(
            osc_base_url()
            . Params::getRequestURI(false, false, false)
        );
        header('Location: ' . osc_admin_base_url(true) . '?page=login');
        exit;
    }
}

/* file end: ./oc-includes/osclass/core/AdminSecBaseModel.php */
