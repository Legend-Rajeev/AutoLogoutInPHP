<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * @property document_m $document_m
 * @property email_m $email_m
 * @property error_m $error_m
 * SET @rank:=0;
update T
set Number=@rank:=@rank+1;
 */
class Admin_Controller extends MY_Controller {

    private $_backendTheme = '';
    private $_backendThemePath = '';

    function __construct() {

        parent::__construct();
        //        die('Please wait for maintenance....'); 
        $this->load->library("session");
        $this->load->helper('language');
        $this->load->helper('date');
        $this->load->helper('form');
        $this->load->helper('traffic');
        $this->load->library('form_validation');

        $this->load->model("signin_m");
        $this->load->model("permission_m");
        $this->load->model("permissionl_m");
        $this->load->model("setting_m");
        $this->load->model("schoolyear_m");
        $this->load->model('classes_m');
        $this->load->model('batch_m');
        $this->load->model('semester_m');
        $this->load->model('subject_m');
        $this->load->model("menu_m");
        $this->load->model("studenthouse_m");
        $this->load->model("changelog_m");
        $this->load->model("changelogprefix_m");
        $this->load->model("printpermission_m");
        //$this->db->cache_on();
        //$this->db->cache_off();
        $this->data["siteinfos"] = $this->setting_m->get_setting(1);
        
        
        $appjs='assets/sw/appjs/'.school_path();//folder
        if (!is_dir($appjs)) {
            mkdir($appjs, 0777, TRUE);
        }
        $exception_uris = array(
            'signin/index',
            'signin/isSignin',
            'signin/indexapi',
            'invoice/OrderResponse',
            'invoice/txnStatusV2',
            'invoice/onlinepaymentresponses',
            'signin/signout',
            'login',
            'signin',
            'signout',
            'logout'
        );
        //  echo "Under maintenance<br>";

        if (!in_array($this->uri->segment(1) . '/' . $this->uri->segment(2), $exception_uris) && $this->signin_m->loggedin() == FALSE) { 
            if ($this->input->is_ajax_request()) {
                exit(json_encode(['status'=>false,'msg'=>'Your are logged out. Please login again!']));
            } else
            {
                echo json_encode(['status'=>false,'msg'=>'Your are logged out. Please login again!']);
                exit(script('window.location.href="' . base_url("signin/index") . '"'));
            }
        }
        
        
        //$this->db->cache_off();
        $this->data['backendTheme'] = strtolower($this->data["siteinfos"]->backend_theme); 
        $this->data['backendThemePath'] = 'assets/initialize/themes/' . strtolower($this->data["siteinfos"]->backend_theme);
        $this->_backendTheme = $this->data['backendTheme'];
        $this->_backendThemePath = $this->data['backendThemePath'];
        //$this->db->cache_on();
        $this->data['topbarschoolyears'] = pluck($this->db->select('schoolyearID,schoolyear')->from('schoolyear')->get()->result(),'obj', 'schoolyearID');
        $this->data["menuclasses"] = pluck($this->db->select('classesID,classes,classes_numeric')->from('classes')->where('active',1)->order_by('classes_numeric','asc')->get()->result(), 'obj','classesID');
        $this->data["allbatchsq"] = pluck($this->db->select('batchID,classesID,batch,active')->from('batch')->order_by('classesID','asc')->order_by('batch','asc')->get()->result(),'obj', 'batchID'); 
        $this->data["batchbyclasses"] = $this->batch_m->batchbyclasses('1');

		$mcyear = $this->db->select('cyearID,cyear,classesID')->from('cyear')->where('active',1)->order_by('cyear','asc')->get()->result();
		$this->data['mcyear'] = pluck($mcyear, 'cyear', 'cyearID');
		$ccyear = [];
		foreach($mcyear as $k=>$v){
			if($v->classesID && !$ccyear[$v->classesID]){
				$ccyear[$v->classesID] = [];
			}
			if($ccyear[$v->classesID] && !$ccyear[$v->cyearID]){
				$ccyear[$v->classesID][$v->cyearID] = [];
			}
			$ccyear[$v->classesID][$v->cyearID] = $v->cyear;
		}
        $this->data["menucyear"] = $ccyear;

        
		$msemester = $this->db->select('semesterID,semester,classesID,batchID,cyearID')->from('semester')->where('active',1)->order_by('semesterID','asc')->get()->result();
		$this->data['msemester'] = pluck($msemester, 'semester', 'semesterID');
		$csemester = [];
		foreach($msemester as $k=>$v){
			if($v->classesID && !$csemester[$v->classesID]){
				$csemester[$v->classesID] = [];
			}
			if($csemester[$v->batchID] && !$csemester[$v->batchID]){
				$csemester[$v->classesID][$v->batchID] = [];
			}
			if($csemester[$v->cyearID] && !$csemester[$v->cyearID]){
				$csemester[$v->classesID][$v->batchID][$v->cyearID] = [];
			}
            $csemester[$v->classesID][$v->batchID][$v->cyearID][$v->semesterID] = $v;
		}
        $this->data["menusemester"] = $csemester;
        // echo '<pre>'; print_r($csemester); exit;
		
        //echo "<pre>";print_r($this->data["allsectionsq"]);die;
        $_SESSION['data']['menuusertype']=$this->data["menuusertype"] = pluck($this->db->select('usertypeID,usertype')->from('usertype')->get()->result(), 'obj', 'usertypeID');
        $this->db->cache_off();
         
        $this->data['all'] = [];
        $this->data['alert'] = [];
        if (!$this->input->is_ajax_request()) {
            $this->createFields(['fieldoption'=>'','value'=>''],'viewjs');
            $i = 0;
            
            $this->data['sitesettings'] = array( 'schooltype' => 'classbase' );
            $this->data['allcountry'] = array( "India" => "India" );
            /* Alert System End......... */
            /* message counter */
            $email = $this->session->userdata('email');
            $usertype = $this->session->userdata('usertype');
            $this->data["firebaseConfig"] = $this->getConfigValue('firebaseConfig', false,['apiKey'=> "AIzaSyBpE7G1ldfeJqadCDW1m_IZ-SHL2Z77TrQ", 'authDomain'=> "svm-291e0.firebaseapp.com", 'projectId'=> "svm-291e0", 'storageBucket'=> "svm-291e0.appspot.com",
            'messagingSenderId'=> "616134665097", 'appId'=> "1:616134665097:web:65f0294c2adda8c779b758", 'measurementId'=> "G-RV8GSFSKBX"])->value;
        }
        $language = $this->session->userdata('lang');
        $this->lang->load('topbar_menu', $language);
        
        


        $module = strtolower($this->uri->segment(1));
        $action = $this->uri->segment(2);
        $permission = '';

        if ($action == 'index' || $action == false) {
            $permission = $module;
        } else {
            $permission = $module . '_' . $action;
        }

        $permissionset = array();
        $userdata = $this->session->userdata;
        $this->db->cache_off();
        if ($this->session->userdata('username') == 'devadmin') {
            $allmodules = $this->permission_m->get_permission();
            if ( ($allmodules)) {
                foreach ($allmodules as $key => $allmodule) {
                    $permissionset['master_permission_set'][trim($allmodule->name)] = $allmodule->active;
                }
                $data = ['get_permission' => TRUE];
                $this->session->set_userdata($data);
                $this->session->set_userdata($permissionset);
            }
            // }
        } else {
            if (isset($userdata['loginuserID']) && !isset($userdata['get_permission'])) {
                if (!$this->session->userdata($permission)) {
                    $user_permission = $this->permission_m->get_modules_with_permission($userdata['usertypeID']);
                    foreach ($user_permission as $value) {
                        $permissionset['master_permission_set'][trim($value->name)] = $value->active;
                    }
                    $permissionset['master_permission_set']['profile'] = 'yes';
                    $data = ['get_permission' => TRUE];
                    $this->session->set_userdata($data);
                    $this->session->set_userdata($permissionset);
                }
            }
        }


        $sessionPermission = $this->session->userdata('master_permission_set');
        if (!$this->input->is_ajax_request()) {
            $this->db->cache_on();
            $_SESSION['data']['menujson_decode'] = $this->menu_m->get_order_by_menu(['status' => 1]);//pluck(, 'obj', 'menuID');
            $this->db->cache_off();
            //            $dbMenus = $this->menuTree(json_decode(json_encode($_SESSION['data']['menujson_decode']), true), $sessionPermission);
            //            $this->data["dbMenus"] = $dbMenus;
           $this->data["Menus_list"] = pluck($_SESSION['data']['menujson_decode'], 'menuName', 'link');
        }
        //echo "<pre>";print_r($sessionPermission);die;
        if ((isset($sessionPermission[$permission]) && $sessionPermission[$permission] == "no")) {
            if ($permission == 'dashboard' && $sessionPermission[$permission] == "no") {
                $url = 'exceptionpage/index';
                if (in_array('yes', $sessionPermission)) {
                    if ($sessionPermission["dashboard"] == 'no') {
                        foreach ($sessionPermission as $key => $value) {
                            if ($value == 'yes') { $url = $key; break; }
                        }
                    }
                } else {
                    redirect(base_url('exceptionpage/index'));
                }
                redirect(base_url($url));
            } else {
                redirect(base_url('exceptionpage/error'));
            }
        }
		
        $file=FCPATH.$appjs.'languages.json';
        if(!file_exists($file)){
            if (!write_file($file, "const languages=".toJSON($this->lang).";")) {
                echo "Failed to write";
            }
        }
        $this->data['appjs']=['languages.json'];
        log_message('error', '[!err] id:' . $this->session->userdata('username') . ',vistit:' . base_url('') . (uri_string()).' ');
    }

    public function checkSessionLogout(){
        if(isset($_SESSION['loginuserID'])){
            if(!isset($_SESSION['LastActivityTime'])){
                $_SESSION['LastActivityTime'] = time() + (15 * 60);
            }else{
                $current_time = time();
                if($current_time > $_SESSION['LastActivityTime']){
                    $this->signin_m->signout();
                    header("Location:" . base_url("signin/index"));
                }else{
                    $_SESSION['LastActivityTime'] = time() + (15 * 60);
                }
            }
        }
    }

    public function _remap($method, $params = array()){

        $this->checkSessionLogout();
        
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $params);
        }

    }

    public function usercreatemail($email = NULL, $username = NULL, $password = NULL) {
        $this->load->library('email');
        $this->email->set_mailtype("html");
        $this->data["siteinfos"] = $this->setting_m->get_setting(1);
        if ($email) {
            $this->email->from($this->data['siteinfos']->email, $this->data['siteinfos']->sname);
            $this->email->to($email);
            $this->email->subject($this->data['siteinfos']->sname);
            $url = base_url();
            $message = "<h2>Welcome to " . $this->data['siteinfos']->sname . "</h2>
	        <p>Please log-in to this website and change the password as soon as possible </p>
	        <p>Website : " . $url . "</p>
	        <p>Username: " . $username . "</p>
	        <p>Password: " . $password . "</p>
	        <br>
	        <p>Once again, thank you for choosing " . $this->data['siteinfos']->sname . "</p>
	        <p>Best Wishes,</p>
	        <p>The " . $this->data['siteinfos']->sname . " Team</p>";
            $this->email->message($message);
            $this->email->send();
        }
    }

    public function viewsendtomail($data = NULL, $viewpath = NULL, $email = NULL, $subject = NULL, $message = NULL, $pagesize = 'a4', $pagetype = 'portrait') {
        $this->load->library('email');
        // $this->load->library('m_pdf');

        $filename = rand(100, 999) . date('ymdHis');
        $this->data['panel_title'] = $this->lang->line('panel_title');
        $html = $this->load->view($viewpath, $this->data, true);
        if ($html != '') {
            $this->email->set_mailtype("html");
            $this->email->from($this->data["siteinfos"]->email, $this->data['siteinfos']->sname);
            $this->email->to($email);
            $this->email->subject($subject);
            $this->email->message($message);
            //$this->email->attach($path);
            if ($this->email->send()) {
                $this->session->set_flashdata('success', $this->lang->line('mail_success'));
            } else {
                $this->session->set_flashdata('error', $this->lang->line('mail_error'));
            }
        }
    }

    function printview($data = NULL, $viewpath = NULL, $mode = 'view', $pagesize = 'a4', $pagetype = 'portrait') {
        $this->data['panel_title'] = $this->lang->line('panel_title');
        $html = $this->load->view($viewpath, $this->data, true);

        $this->load->library('mhtml2pdf');

        $this->mhtml2pdf->folder('uploads/' . school_path() . 'report/');
        $this->mhtml2pdf->filename('Report');

        $this->mhtml2pdf->html($html);
        return $this->mhtml2pdf->create($mode, $data);
    }

    public function getAllCountry() {
        $country = array(
            "India" => "India",
        );
        return $country;
    }

    public function getPromoteMessage() {
        $promotemessage = array(
            "0" => "Select Promote Message",
            "Promoted To Next Higher Class" => "Promoted To Next Higher Class",
            "Conditionally Promoted" => "Conditionally Promoted",
            "Allowed For Re-Test" => "Allowed For Re-Test"
        );
        return $promotemessage;
    }

    public function send_sms_m($userID, $usertypeID, $message, $phone, $ar = null, $smstypefilter = null) {
        $this->load->library("smsshop");
        $status = $this->smsshop->send($phone, $message);
        $insert = [];
        $insert["smssentlog"] = $message;
        $insert["create_userID"] = $this->session->loginuserID;
        $insert["create_usertypeID"] = $this->session->usertypeID;
        $insert["smsgateway"] = 'cantsay';
        $insert["sentto_userID"] = $userID;
        $insert["sentto_usertypesID"] = $usertypeID;
        $insert["status"] = ($status > 0 ? 1 : 0);
        $insert["phone"] = $phone;
        $insert["smstypefilter"] = ($smstypefilter == null ? 'allsms' : $smstypefilter);
        $insert["create_date"] = date('Y-m-d H:i:s');

        $sql = $this->db->insert_string('smssentlog', $insert);
        if ($ar == null) {
            $this->db->query($sql);
            return $status;
        }//
        else {
            return $sql;
        }
    }

    //menu link should be same as for permission name
    public function menuTree($dataset, $sessionPermission) {
        //echo "<pre>";print_r($sessionPermission);die;
        $tree = array();
        foreach ($dataset as $id => &$node) {
            if (@$node['link'] == '#' || (isset($sessionPermission[@$node['link']]) && $sessionPermission[@$node['link']] != "no" && permissionChecker(@$node['link'] . '_menu') )) {
                if ($node['parentID'] == 0) {
                    $tree[$id] = &$node;
                } else {
                    if (!isset($dataset[$node['parentID']]['child'])) {
                        $dataset[$node['parentID']]['child'] = array();
                    }

                    $dataset[$node['parentID']]['child'][$id] = &$node;
                }
            }
        }
//        die;
        return $tree;
    }
    
    private function removeExtras($x){
        if ($x) {
            foreach ($x as $key => $value) {
                
                if (is_array($value)) {
                    
                    if (isset($value['icon'])) {
                        unset($x[$key]['icon']);
                    }
                    if (isset($value['pullRight'])) {
                        unset($x[$key]['pullRight']);
                    }
                    if (isset($value['status'])) {
                        unset($x[$key]['status']);
                    }
                    if (isset($value['priority'])) {
                        unset($x[$key]['priority']);
                    }
                    if (isset($value['parentID'])) {
                        unset($x[$key]['parentID']);
                    }
                    if (isset($value['menuID'])) {
                        unset($x[$key]['menuID']);
                    }
                    if (isset($value['menuName'])) {
                        $x[$key]['menuName'] = ($this->lang->line('menu_' . $value['menuName']) != NULL || $this->lang->line('menu_' . $value['menuName']) != '' ? $this->lang->line('menu_' . $value['menuName']) : ucfirst($value['menuName']));
                    }
                }else
                if (is_object($value)) {
                    
                    if (isset($value->icon)) {
                        unset($x[$key]->icon);
                    }
                    if (isset($value->pullRight)) {
                        unset($x[$key]->pullRight);
                    }
                    if (isset($value->status)) {
                        unset($x[$key]->status);
                    }
                    if (isset($value->priority)) {
                        unset($x[$key]->priority);
                    }
                    if (isset($value->parentID)) {
                        unset($x[$key]->parentID);
                    }
                    if (isset($value->menuID)) {
                        unset($x[$key]->menuID);
                    }
                    if (isset($value->menuName)) {
                        $x[$key]->menuName = ($this->lang->line('menu_' . $value->menuName) != NULL || $this->lang->line('menu_' . $value->menuName) != '' ? $this->lang->line('menu_' . $value->menuName) : ucfirst($value->menuName));
                    }
                }
                
            }
            
        }
        return $x;
    }

        public function xmenuList() {
        //$x[]=['menuName'=>'Change Password','link'=>'signin/cpassword'];
        $x = $this->menuTree(json_decode(json_encode(pluck($_SESSION['data']['menujson_decode'], 'obj', 'menuID')), true), $this->session->userdata('master_permission_set')); 
        if ($x) {
            foreach ($x as $key => $value) {
                if (is_object($value)) {
                    if ($value->link == "#" && !isset($value->child)) {
                        unset($x[$key]);
                    }
                } if (is_array($value)) {
                    if ($value['link'] == "#" && !isset($value['child'])) {
                        unset($x[$key]);
                    }
                }
            }
            foreach ($x as $key => $value) {
                
                if (is_array($value)) {
                    
                    if (isset($value['icon'])) {
                        unset($x[$key]['icon']);
                    }
                    if (isset($value['pullRight'])) {
                        unset($x[$key]['pullRight']);
                    }
                    if (isset($value['status'])) {
                        unset($x[$key]['status']);
                    }
                    if (isset($value['priority'])) {
                        unset($x[$key]['priority']);
                    }
                    if (isset($value['parentID'])) {
                        unset($x[$key]['parentID']);
                    }
                    if (isset($value['menuID'])) {
                        unset($x[$key]['menuID']);
                    }
                    if (isset($value['child'])) {
                        foreach ($value['child'] as $key2 => $value2){
                            if (is_array($value2)) {
                                
                                if (isset($value2['icon'])) {
                                    unset($x[$key]['child'][$key2]['icon']);
                                }
                                if (isset($value2['pullRight'])) {
                                    unset($x[$key]['child'][$key2]['pullRight']);
                                }
                                if (isset($value2['status'])) {
                                    unset($x[$key]['child'][$key2]['status']);
                                }
                                if (isset($value2['priority'])) {
                                    unset($x[$key]['child'][$key2]['priority']);
                                }
                                if (isset($value2['parentID'])) {
                                    unset($x[$key]['child'][$key2]['parentID']);
                                }
                                if (isset($value2['menuID'])) {
                                    unset($x[$key]['child'][$key2]['menuID']);
                                } 
                                if (isset($value2['menuName'])) {
                                    $x[$key]['child'][$key2]['menuName'] = ($this->lang->line('menu_' . $value2['menuName']) != NULL || $this->lang->line('menu_' . $value2['menuName']) != '' ? $this->lang->line('menu_' . $value2['menuName']) : ucfirst($value2['menuName']));
                                }
//                                echo "::is_array";print_r($value2);
//                                echo "<pre>::is_array";print_r($x[$key]);
//                                die;
                            } 
                        }
                    }
                    if (isset($value['menuName'])) {
                        $x[$key]['menuName'] = ($this->lang->line('menu_' . $value['menuName']) != NULL || $this->lang->line('menu_' . $value['menuName']) != '' ? $this->lang->line('menu_' . $value['menuName']) : ucfirst($value['menuName']));
                    }
                } 
                
            }
        }
        //$this->removeExtras($x);
        exit(toJSON($x));
    }

    public function permission($permission, $errorinfo = 'Invalid permission') {
        if (is_array($permission)) {
            foreach ($permission as $value) {
                if (!permissionChecker($value)) {
                    $this->data["subview"] = "error";
                    $this->data["errorinfo"] = $errorinfo;
                    $this->load->view('_layout_main', $this->data);
                    die;
                }
            }
        } else if (!permissionChecker($permission)) {
            $this->data["subview"] = "error";
            $this->data["errorinfo"] = $errorinfo;
            $this->load->view('_layout_main', $this->data);
            die;
        }
    }

    public function rerror($st = 'error') {
        redirect(base_url($st));
    }

    //----change log
    //-- only compared value
    public function savechangelog_($oldarr, $newarr, $rowID, $note, $approvedby, $changeonview, $usertypeID = 0) {
        $changes = [];
        if (count($oldarr) && count($newarr)) {
            foreach ($oldarr as $key => $value) {
                if (key_exists($key, $newarr) && $newarr[$key] != $value) {
                    $changes[$key] = [$value, $newarr[$key]];
                }
            }
        }
        $jsonstring = '';
        if (count($changes)) {
            $jsonstring = json_encode($changes);
        }
        if ($jsonstring != '' && $note != '' && $changeonview != '' && $approvedby != '') {
            $id = 0;
            $q = $this->db->query("select referenceno from changelog where reference='" . trim(strtolower($changeonview)) . "' order by referenceno desc limit 1")->row();
            if (($q)) {
                $id = (int)$q->referenceno;
            }$id++;

            $data = ['referenceno' => $id,
                'changelog' => $jsonstring,
                'rowID' => $rowID,
                'other' => $note,
                'usertypeID' => $usertypeID,
                'approvedby' => $approvedby,
                'reference' => $changeonview,
                'schoolyearID' => $this->data['siteinfos']->school_year,
                'update_date' => date('Y-m-d H:i:s'),
                'create_date' => date('Y-m-d H:i:s'),
                'create_userID' => $this->session->userdata('loginuserID'),
                'update_userID' => $this->session->userdata('loginuserID'),
                'update_usertypeID' => $this->session->userdata('usertypeID'),
                'create_usertypeID' => $this->session->userdata('usertypeID')
            ];

            $k = $this->changelog_m->insert_($data);
            if ($k > 0) {
                return $id;
            } else {
                return 0;
            }
        } else {
            return 0;
        }
    }

    public function getchangelog_no($changeonview) {
        if ($changeonview != '') {
            $id = 0;
            $q = $this->db->query("select referenceno from changelog where reference='" . trim(strtolower($changeonview)) . "' order by referenceno desc limit 1")->row();
            if (($q)) {
                $id = $q->referenceno + 1;
            } else {
                $id = 1;
            }
            return $id;
        } else {
            return 0;
        }
    }

    public function getchangelogs($changeonview, $select = [], $where = null, $orderby = 'referenceno desc') {
        if ($changeonview != '') {
            if (!count($select)) {
                $this->db->select('*');
            } else {
                $this->db->select(implode(',', $select));
            }
            $this->db->select("" . is_moreinfo() . " as moreinfo");
            $this->db->from("changelog");
            $this->db->where(['reference' => $changeonview]);
            if ($where != null) {
                $this->db->where($where);
            }

            $this->db->order_by($orderby);
            return $this->db->get()->result();
        } else {
            return [];
        }
    }

    public function getchangelog($changeonview, $referenceno) {
        if ($changeonview != '' && $referenceno > 0) {
            return $this->db->query("select *," . is_moreinfo() . " as moreinfo from changelog where reference='" . trim(strtolower($changeonview)) . "' and referenceno='" . trim($referenceno) . "' ")->row();
        } else {
            return [];
        }
    }

    //---------
    public function updatedata($data = []) {
        $data['update_date'] = date('Y-m-d H:i:s');
        $data['update_userID'] = $this->session->userdata('loginuserID');
        $data['update_usertypeID'] = $this->session->userdata('usertypeID');
        return $data;
    }

    public function insertdata($data = []) {
        if (!$data) {
            $data = [];
        }
        $data['create_date'] = date('Y-m-d H:i:s');
        $data['create_userID'] = $this->session->userdata('loginuserID');
        $data['create_usertypeID'] = $this->session->userdata('usertypeID');

        return $data;
    }

    public function getusertypes() {
        $q = $this->db->query("select usertype,usertypeID from usertype where active=1")->result();
        return pluck($q, 'usertype', 'usertypeID');
    }

    public function getusersontype($usertypes) {
        $users = [];
        foreach ($usertypes as $k => $v) {
            if ($k == 1) {
                $users[1] = pluck($this->db->query("select systemadminID,concat(ifnull(employeecode,'no empcode'),' :: ',ifnull(name,'no name'))as userinfo from systemadmin  ")->result(), 'userinfo', 'systemadminID');
            } elseif ($k == 2) {
                $users[2] = pluck($this->db->query("select teacherID,concat(ifnull(employeecode,'no empcode'),' :: ',ifnull(name,'no name'))as userinfo from teacher where usertypeID=2 ")->result(), 'userinfo', 'teacherID');
            } elseif ($k == 3) {
                $users[3] = pluck($this->db->query("select studentID,concat(ifnull(registerNO,'no regno.'),' :: ',ifnull(name,'no name'))as userinfo from student where active=1 ")->result(), 'userinfo', 'studentID');
            } elseif ($k == 4) {
                $users[4] = [];
            } else {
                $users[$k] = pluck($this->db->query("select userID,concat(ifnull(employeecode,'no empcode'),' :: ',ifnull(name,'no name'))as userinfo from user where  usertypeID=$k ")->result(), 'userinfo', 'userID');
            }
        }
        return $users;
    }

    public function createget($array = []) {
        $tmp = [];
        if (!empty($array)) {
            foreach ($array as $value) {
                $this->data[$value] = $tmp[$value] = $this->input->get($value);
            }
        }return $tmp;
    }

    public function createpost($array = []) {
        $tmp = [];
        if (!empty($array)) {
            foreach ($array as $value) {
                $this->data[$value] = $tmp[$value] = $this->signin_m->mypost($value);
            }
        }return $tmp;
    }

    public function createFields($array, $table, $primary = true) {
        if (!empty($array)) {
            if (!$this->db->table_exists($table)) {
                $this->db->query("create table IF NOT EXISTS `$table` " . ($primary ? " ("
                                . "`id` int(11) NOT NULL auto_increment, "
                                . "PRIMARY KEY  (`id`)"
                                . ")  ;" : "(`id` int(11) NULL )"));
            }
            foreach ($array as $k => $value) {
                if (!$this->db->field_exists($k, $table)) {
                    $this->db->query("alter table $table add $k text null");
                }
            }
        }
    }

    public function mypost($value) {
        return $this->signin_m->mypost($value);
    }

    public function date_validate($date) {
        if ($date) {
            if (strlen($date) < 10) {
                $this->form_validation->set_message("date_validate", "%s is not valid dd-mm-yyyy");
                return FALSE;
            } else {
                $arr = explode("-", $date);
                $dd = $arr[0];
                $mm = $arr[1];
                $yyyy = $arr[2];
                if (checkdate($mm, $dd, $yyyy)) {
                    return TRUE;
                } else {
                    $this->form_validation->set_message("date_validate", "%s is not valid dd-mm-yyyy");
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

    public function reurl($param = 'dashboard') {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : base_url($param);
    }

    public function intdata($array) {
        $tmp = [];
        if (!empty($array)) {
            foreach ($array as $k => $v) {
                if ($k == '0' || $k > 0) {
                    $tmp[$v] = '';
                    $this->data[$v] = $tmp[$v];
                } else {
                    $tmp[$k] = $v;
                    $this->data[$k] = $tmp[$k];
                }
            }
        }return $tmp;
    }

    protected function getIntrange($genattime) {
        $stringmonth = array();
        $out = substr($genattime, -3);
        foreach (get_range($this->feegen_m->get_feemon_from_invoice($genattime)) as $v) {
            if (explode('-', $v)) {
                $temp = array();
                foreach (explode('-', $v) as $w) {
                    $temp[] = $w;
                }
                if (count($temp) > 1) {
                    $stringmonth[] = $temp[0] . '-' . $temp[1];
                } else {
                    $stringmonth[] = $temp[0];
                }
            } else {
                $stringmonth[] = $w;
            }
        }
        $i = count($stringmonth);
        if ($i > 1) {
            $stringmonth[$i - 1] = $stringmonth[$i - 1] . $out;
        } else {
            $stringmonth[0] = $stringmonth[0] . $out;
        }

        return $stringmonth;
    }

    public function dslip($slp, $sid,$pid=0) {
        if ($this->data['siteinfos']->mark_1 == 'yes') {
            return demandslp($slp, $sid,$pid);
        } else {
            return $sid . "S" . implode('x', $this->getIntrange($slp));
        }
    }

    public function delbackup($data, $ftable, $execute = false) {
        $sql[] = $k = $this->student_m->getinsertq(['deletedtrash' => json_encode($data), 'ftable' => $ftable, 'create_date' => date('Y-m-d H:i:s'), 'create_userID' => $this->session->userdata('loginuserID'), 'create_usertypeID' => $this->session->userdata('usertypeID')], 'deletedtrash');
        if ($execute) {
            return batchExecute($sql, $this->db);
        } return $k;
    }

    public function ajaxunlinkthisphoto() {
        if ($_POST) {
            $photo = $this->input->post('photo');
            if ($photo !== 'default.png') {
                if (file_exists(FCPATH . 'uploads/' . $this->SCHOOL_PATH . 'images/' . $photo)) {
                    $result = file_put_contents('mvc/logs/deletedphotoby_' . $photo . '__' . $this->session->userdata('username'), 'deletedphotoby_' . $photo . '__' . $this->session->userdata('username') . '__date:' . date('Y-m-d H:i:s'));
                    unlink(FCPATH . 'uploads/' . $this->SCHOOL_PATH . 'images/' . $photo);
                    echo json_encode(['status' => 'success']);
                    // echo json_encode(['status'=>'error','msg'=>'Not found '.$photo]);
                } else {
                    echo json_encode(['status' => 'error', 'msg' => 'Not found ' . $photo]);
                }
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'You can not delete default image. ' . $photo]);
            }exit();
        }
    }

    public function getInputPost($param = [], $post = []) {
        $ret = [];
        if ($param) {
            foreach ($param as $value) {
                $ret[$value] = (isset($post[$value]) ? $post[$value] : null);
            }
        }
        return $ret;
    }

    function getConfigValueVer_2($key, $schoolyearID, $set = false, $default = null) {
        if (is_array($default)) {
            $default = json_encode($default);
        }
        if (strlen($key) >= 2) {
            $this->createFields(['schoolyearID' => '', 'fieldoption' => '', 'value' => ''], 'mastersettingv2');
            $q = $this->db->select('value')->from('mastersettingv2')->where('fieldoption', $key)->where('schoolyearID', $schoolyearID)->get()->row();
            if (!$q && $default) {
                $this->db->insert('mastersettingv2', ['fieldoption' => $key, 'value' => ($default ? $default : null), 'schoolyearID' => $schoolyearID]);
                $q = $this->db->select('value')->from('mastersettingv2')->where('fieldoption', $key)->where('schoolyearID', $schoolyearID)->get()->row();
            } else if ($q && $default && $set) {
                $this->db->set('value', $default)->where('fieldoption', $key)->where('schoolyearID', $schoolyearID)->update('mastersettingv2');
                $q = $this->db->select('value')->from('mastersettingv2')->where('fieldoption', $key)->where('schoolyearID', $schoolyearID)->get()->row();
            }
            return $q;
        }
        return false;
    }

    function ajax_GetConfigValue() {
        if (!$this->input->is_ajax_request()) {
            exit('No direct access');
        }
        extract($this->createpost(['key', 'set', 'default']));
        if (is_array($default)) {
            $default = toJSON($default);
        }
        if (strlen($key) >= 2) {
            $q = $this->db->get_where('mastersetting', ['fieldoption' => $key])->row();
            if (!$q) {
                $this->db->insert('mastersetting', ['fieldoption' => $key, 'value' => ($default ? $default : null)]);
                $q = $this->db->get_where('mastersetting', ['fieldoption' => $key])->row();
            } else if ($default && $set == true) {
                $this->db->set('value', $default)->where('fieldoption', $key)->update('mastersetting');
                $q = $this->db->get_where('mastersetting', ['fieldoption' => $key])->row();
            }
            $array=(array)$q;
            $array['status']=true;
            exit(toJSON($array));
        }
        exit(toJSON(['status' => false]));
    }

    function ajax_GetViewjsValue() {
        if (!$this->input->is_ajax_request()) {
            exit('No direct access');
        }
        extract($this->createpost(['key', 'set']));
        $default=$this->input->post('default',false);
        if (is_array($default)) {
            $default = toJSON($default);
        }
        if (strlen($key) >= 2) {
            $dt = json_decode(file_get_contents('./assets/sw/viewjson.json'),true); 
            $q = [];
            if (!isset($dt[$key])) { 
                $dt[$key]=($default ? $default : null);
                file_put_contents('./assets/sw/viewjson.json', toJSON($dt));
                $q = ['fieldoption' => $key,'value'=>$dt[$key]];
            } else if ($default && $set == true) {
                $dt[$key]=$default; 
                file_put_contents('./assets/sw/viewjson.json', toJSON($dt));
                $q = ['fieldoption' => $key,'value'=>$dt[$key]];
            }else{
                $q = ['fieldoption' => $key,'value'=>$dt[$key]];
            }
            $array= $q;
            $array['status']=true;
            exit(toJSON($array));
        }
        exit(toJSON(['status' => false]));
    }

    function getConfigValue($key, $set = false, $default = '') {
        
        if (strlen($key) >= 2) {
            if($default&&is_array($default)){$default= toJSON($default);}
            $q = $this->db->get_where('mastersetting', ['fieldoption' => $key])->row();
            if (!$q) {
                $this->db->insert('mastersetting', ['fieldoption' => $key, 'value' => ($default ? $default : null)]);
                $q = $this->db->get_where('mastersetting', ['fieldoption' => $key])->row();
            } else if ($default && $set) {
                $this->db->set('value', $default)->where('fieldoption', $key)->update('mastersetting');
                $q = $this->db->get_where('mastersetting', ['fieldoption' => $key])->row();
            }
            return $q;
        }
        return false;
    }

    function getViewjsValue($key, $set = false, $default = '') {
        
        if (strlen($key) >= 2) {
            if($default&&is_array($default)){$default= toJSON($default);}
            $dt = json_decode(file_get_contents('./assets/sw/viewjson.json'),true); 
            $q = [];
            if (!isset($dt[$key])) {
                $dt[$key]=($default ? $default : null);
                file_put_contents('./assets/sw/viewjson.json', toJSON($dt));
                $q = ['fieldoption' => $key,'value'=>$dt[$key]];
            } else if ($default && $set) {
                $dt[$key]=$default; 
                file_put_contents('./assets/sw/viewjson.json', toJSON($dt));
                $q = ['fieldoption' => $key,'value'=>$dt[$key]];
            }else{
                $q = ['fieldoption' => $key,'value'=>$dt[$key]];
            }
            return (object)$q;
        }
        return false;
    }
	
    function get_send_sms($crl, $phone, $sms) {
        $arr['$phone$'] = $phone;
        $arr['$sms$'] = $sms;
        $pArray = explode('&', parse_url(trim($crl), PHP_URL_QUERY));
        $post = [];
        foreach ($pArray as $value) {
            $ex = explode('=', $value);
            $post[trim($ex[0])] = str_replace(array_keys($arr), $arr, trim($ex[1]));
        }
        $url = 'https://' . trim(parse_url($crl, PHP_URL_HOST)) . trim(parse_url($crl, PHP_URL_PATH));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        // Allowing cUrl funtions 20 second to execute
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        // Waiting 20 seconds while trying to connect
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        $response_string = curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        if (is_devadmin()) {
            if ($response_string != '') {
                return trim($response_string);
            }else
            return 'Failed , Devadmin:url:' . $url . ', `' . json_encode($post) . '`,  Respon:`'.$response_string.'`<br>' . print_r($curl_info,true);
        } else {
            if ($response_string != '') {
                return trim($response_string);
            } else {
                return 'Failed ';
            }
        } 
    }
    
    public function insertnoticelog($userID, $usertypeID, $smstypefilter="normal", $status, $msg_title="", $msg_body="", $img_url="", $open_url="https://rpspg.myskoolerp.co.in/view_notice"){
        $arr = [
            "msg_title"=>$msg_title,
            "msg_body"=>$msg_body,
            "img_url"=>$img_url,
            "open_url"=>$open_url,
            "smstypefilter"=>$smstypefilter,
            "userID"=>$userID,
            "usertypeID"=>$usertypeID,
            "senderID"=>$this->session->userdata("loginuserID"),
            "sendertypeID"=>$this->session->userdata("usertypeID"),
            "date"=>date('Y-m-d H:i:s'),
            "view_status"=>"0",
            "status"=>$status
        ];
        $q = $this->db->insert("noticesentlog", $arr);
        return $q;
    }
    
     // Send Push Notification
    public function send_notice($s_token, $n_title="Notification",$n_body="", $img="", $open_url="https://rpspg.myskoolerp.co.in/view_notice"){
        require_once(APPPATH.'../../google_api/vendor/autoload.php');
        if($_SERVER["REQUEST_METHOD"] == "POST") {
            $token = ''.$s_token.'';
            $client = new Google_Client();
            $client->useApplicationDefaultCredentials();
            $client->setAuthConfig(APPPATH.'notice-api/rpspg-43ba9-firebase-adminsdk-jp7n8-2245e2ea61.json');
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $httpClient = $client->authorize();
            $project = "rpspg-43ba9";
            $message = ["message" => ["token" => $token,"notification" => ["body" => $n_body,"title" => $n_title, "image"=>$img],"data" => ["openurl" => $open_url]]];
            
            // Send the Push Notification - use $response to inspect success or errors
            $response = $httpClient->post("https://fcm.googleapis.com/v1/projects/".$project."/messages:send", ['json' => $message]);
            // return json_encode($response->getBody()->getContents());
            return json_encode($response->getBody()->getContents());
        }
    }

    public function getHelp() {
        $reference = trim($this->input->post('reference'));
        if ($reference) {
            $fil = './mvc/logs/helpme/' . $reference . '.txt';
            if (!file_exists($fil)) {
                file_put_contents($fil, '<p>Instructions</p>');
            }
            exit(file_get_contents($fil));
        }
        echo "";
    }
    
    public function trashInvoiceImages(){
        if (!$this->input->is_ajax_request()) {
            exit('Direct Access Not Allowed');
        }
        $image=$this->input->post('image',FALSE); 
        $ref=$this->input->post('ref');
        $this->db->cache_off(); 
        $this->db->query("ALTER TABLE `invoice` MODIFY COLUMN `password` text null;");
        if (!$image) {
            echo json_encode(['status'=>false,'msg'=>'required image']);
        }else
        if($this->db->insert('invoice',['username'=>'file_'.$ref,'password'=>$image,'create_date'=>date('Y-m-d H:i:s')])){
            echo json_encode([ 'status'=>true,'msg'=>'Success','d'=> $image ]);
        }else{
            echo json_encode(['status'=>false,'msg'=>'Failed to insert']);
        } 
    }

    // public function 
}
