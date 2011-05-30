<?php if ( ! defined('ABS_PATH')) exit('ABS_PATH is not loaded. Direct access is not allowed.');

    /*
     *      OSCLass – software for creating and publishing online classified
     *                           advertising platforms
     *
     *                        Copyright (C) 2010 OSCLASS
     *
     *       This program is free software: you can redistribute it and/or
     *     modify it under the terms of the GNU Affero General Public License
     *     as published by the Free Software Foundation, either version 3 of
     *            the License, or (at your option) any later version.
     *
     *     This program is distributed in the hope that it will be useful, but
     *         WITHOUT ANY WARRANTY; without even the implied warranty of
     *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     *             GNU Affero General Public License for more details.
     *
     *      You should have received a copy of the GNU Affero General Public
     * License along with this program.  If not, see <http://www.gnu.org/licenses/>.
     */

    class CWebAjax extends BaseModel
    {
        function __construct() {
            parent::__construct() ;
        }

        //Business Layer...
        function doModel() {
            //specific things for this class
            switch ($this->action)
            {
                case 'bulk_actions':
                break;
                
                case 'regions': //Return regions given a countryId
                    $regions = Region::newInstance()->getByCountry(Params::getParam("countryId"));
                    echo json_encode($regions);
                    break;
                
                case 'cities': //Returns cities given a regionId
                    $cities = City::newInstance()->getByRegion(Params::getParam("regionId"));
                    echo json_encode($cities);
                    break;
                
                case 'location': // This is the autocomplete AJAX
                    $cities = City::newInstance()->ajax(Params::getParam("term"));
                    echo json_encode($cities);
                    break;
                    
                case 'delete_image': // Delete images via AJAX
                    $id     = Params::getParam('id') ;
                    $item   = Params::getParam('item') ;
                    $code   = Params::getParam('code') ;
                    $secret = Params::getParam('secret') ;
                    $json = array();

                    if( Session::newInstance()->_get('userId') != '' ){
                        $userId = Session::newInstance()->_get('userId');
                        $user = User::newInstance()->findByPrimaryKey($userId);
                    }else{
                        $userId = null;
                        $user = null;
                    }

                    // Check for required fields
                    if ( !( is_numeric($id) && is_numeric($item) && preg_match('/^([a-z0-9]+)$/i', $code) ) ) {
                        $json['success'] = false;
                        $json['msg'] = _m("The selected photo couldn't be deleted, the url doesn't exist");
                        echo json_encode($json);
                        return false;
                    }

                    $aItem = Item::newInstance()->findByPrimaryKey($item);

                    // Check if the item exists
                    if(count($aItem) == 0) {
                        $json['success'] = false;
                        $json['msg'] = _m('The item doesn\'t exist');
                        echo json_encode($json);
                        return false;
                    }

                    // Check if the item belong to the user
                    if($userId != null && $userId != $aItem['fk_i_user_id']) {
                        $json['success'] = false;
                        $json['msg'] = _m('The item doesn\'t belong to you');
                        echo json_encode($json);
                        return false;
                    }

                    // Check if the secret passphrase match with the item
                    if($userId == null && $secret != $aItem['s_secret']) {
                        $json['success'] = false;
                        $json['msg'] = _m('The item doesn\'t belong to you');
                        echo json_encode($json);
                        return false;
                    }

                    // Does id & code combination exist?
                    $result = ItemResource::newInstance()->getResourceSecure($id, $code) ;

                    if ($result > 0) {
                        // Delete: file, db table entry
                        osc_deleteResource($id);
                        ItemResource::newInstance()->delete(array('pk_i_id' => $id, 'fk_i_item_id' => $item, 's_name' => $code) );

                        $json['msg'] =  _m('The selected photo has been successfully deleted') ;
                        $json['success'] = 'true';
                    } else {
                        $json['msg'] = _m("The selected photo couldn't be deleted") ;
                        $json['success'] = 'false';
                    }

                    echo json_encode($json);
                    return true;
                    break;
                    
                case 'alerts': // Allow to register to an alert given (not sure it's used on admin)
                    $alert = Params::getParam("alert");
                    $email = Params::getParam("email");
                    $userid = Params::getParam("userid");
                    
                    if($alert!='' && $email!='') {

                        if( preg_match("/^[_a-z0-9-+]+(\.[_a-z0-9-+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/",$email) ) {

                            $secret = osc_genRandomPassword();
                            
                            if( Alerts::newInstance()->createAlert($userid, $email, $alert, $secret) ) {
                                
                                if( (int)$userid > 0 ) {
                                    $user = User::newInstance()->findByPrimaryKey($userid);
                                    if($user['b_active']==1 && $user['b_enabled']==1) {
                                        Alerts::newInstance()->activate($email, $secret);
                                        echo '1';
                                        return true;
                                    } else {
                                        echo '-1';
                                        return false;
                                    }
                                } else {
                                    $user['s_name'] = "";
                                    
                                    // send alert validation email
                                    $prefLocale = osc_language() ;
                                    $page = Page::newInstance()->findByInternalName('email_alert_validation') ;
                                    $page_description = $page['locale'] ;

                                    $_title = $page_description[$prefLocale]['s_title'] ;
                                    $_body  = $page_description[$prefLocale]['s_text'] ;

                                    $validation_link  = osc_user_activate_alert_url( $secret, $email );

                                    $words = array() ;
                                    $words[] = array('{USER_NAME}'    , '{USER_EMAIL}', '{VALIDATION_LINK}') ;
                                    $words[] = array($user['s_name']  , $email        , $validation_link ) ;
                                    $title = osc_mailBeauty($_title, $words) ;
                                    $body  = osc_mailBeauty($_body , $words) ;

                                    $params = array(
                                        'subject' => $_title
                                        ,'to' => $email
                                        ,'to_name' => $user['s_name']
                                        ,'body' => $body
                                        ,'alt_body' => $body
                                    ) ;

                                    osc_sendMail($params) ;
                                }

                                echo "1";
                            } else {
                                echo "0";
                            }
                            return true;
                        } else {
                            echo '-1';
                            return false;
                        }
                    }
                    echo '0';
                    return false;
                    break;
                    
                case 'runhook': //Run hooks
                    $hook = Params::getParam("hook");
                    switch ($hook) {

                        case 'item_form':
                            $catId = Params::getParam("catId");
                            if($catId!='') {
                                osc_run_hook("item_form", $catId);
                            } else {
                                osc_run_hook("item_form");
                            }
                            break;
                            
                        case 'item_edit':
                            $catId = Params::getParam("catId");
                            $itemId = Params::getParam("itemId");
                            osc_run_hook("item_edit", $catId, $itemId);
                            break;
                            
                        default:
                            if($hook=='') { return false; } else { osc_run_hook($hook); }
                            break;
                    }
                    break;
                    
                case 'custom': // Execute via AJAX custom file
                    $ajaxfile = Params::getParam("ajaxfile");
                    if($ajaxfile!='') {
                        require_once osc_plugins_path() . $ajaxfile;
                    } else {
                        echo json_encode(array('error' => __('no action defined')));
                    }
                    break;
                    
                default:
                    echo json_encode(array('error' => __('no action defined')));
                    break;
            }
        }
        
        //hopefully generic...
        function doView($file) {
            osc_run_hook("before_html");
            osc_current_web_theme_path($file) ;
            osc_run_hook("after_html");
        }
    }

?>