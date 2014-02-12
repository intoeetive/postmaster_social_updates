<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Facebook
 *
 * Allows you to post notifications to Facebook
 * Social Update module is required
 *
 * @author		Yuri Salimovskiy
 * @link 		http://www.intoeetive.com/
 * @version		1.0
 */
 

class Facebook_postmaster_service extends Base_service {

	public $name = 'Facebook';
    
	public $default_settings = array(
        'post_to'	=> ''
	);

	public $fields = array(
		'post_to' => array(
			'type'  => 'select',
			'id'	=> 'post_to',
			'label' => 'Facebook account/page',
			'settings' => array(
				'options' => array(
					'' => ''
				)		
			)
		),
	);

	public $description = 'Post notifications to Facebook. Social Update must be installed and configured.';
    
    public $su_settings = array();

	public function __construct()
	{
		parent::__construct();
        //first of all, is Social Update installed?
        if ($this->EE->db->table_exists('social_update_settings'))
		{
			$query = $this->EE->db->select('settings')
							->from('social_update_settings')
		                    ->where('site_id', $this->EE->config->item('site_id'))
		                    ->limit(1)
							->get();
			if ($query->num_rows()>0)
			{
				$this->su_settings = unserialize($query->row('settings'));
			}
		}
        
        //if yes, fetch names of Facebook accounts
        foreach ($this->su_settings as $setting_name=>$setting)
        {
            if (is_array($setting) && $setting_name!='trigger_statuses')
			{
				if ($setting["provider"]!='' && $setting['username']!='')
   	            {
                    $this->EE->db->select()
                        ->from('social_update_accounts')
                        ->where('service', 'facebook')
                        ->where('userid', $setting['username'])
                        ->limit(1);
                    $display_name_q = $this->EE->db->get();
                    if ($display_name_q->num_rows()>0)
                    {
                        $this->fields['post_to']['settings']['options'][$setting['app_id']] = $display_name_q->row('display_name');
                    }
                }
            }
        }
        
	}



	public function send($parsed_object, $parcel)
	{		
		$settings = $this->get_settings();

		$message = strip_tags($parsed_object->message);

		if(isset($parsed_object->plain_message) && !empty($parsed_object->plain_message))
		{
			$message = $parsed_object->plain_message;
		}

        $post_params = array(
			'key'=>$this->su_settings["{$settings->post_to}"]['app_id'], 
			'secret'=>$this->su_settings["{$settings->post_to}"]['app_secret']
		);

        $this->EE->load->add_package_path(PATH_THIRD.'social_update/');
        $this->EE->load->library('facebook_oauth', $post_params);

        $remote_post = $this->EE->facebook_oauth->post(
			'', 
			$message, 
			$this->su_settings["{$settings->post_to}"]['token'], 
			$this->su_settings["{$settings->post_to}"]['token_secret'], 
			$this->su_settings["{$settings->post_to}"]['username']
		); 
   
        $this->EE->load->remove_package_path(PATH_THIRD.'social_update/');
        
        if (!empty($remote_post) && $remote_post['remote_user']!='' && $remote_post['remote_post_id']!='')
        {
        	$success = POSTMASTER_SUCCESS;
        }
        else
        {
            $success = POSTMASTER_FAILED;
        }
        
        $to_name = '';
        $this->EE->db->select('display_name')
            ->from('social_update_accounts')
            ->where('service', 'facebook')
            ->where('userid', $settings->post_to)
            ->limit(1);
        $display_name_q = $this->EE->db->get();
        if ($display_name_q->num_rows()>0)
        {
            $to_name = $display_name_q->row('display_name');
        }
            

		return new Postmaster_Service_Response(array(
			'status'     => $success,
			'parcel_id'  => $parcel->id,
			'channel_id' => isset($parcel->channel_id) ? $parcel->channel_id : FALSE,
			'author_id'  => isset($parcel->entry->author_id) ? $parcel->entry->author_id : FALSE,
			'entry_id'   => isset($parcel->entry->entry_id) ? $parcel->entry->entry_id : FALSE,
			'gmt_date'   => $this->now,
			'service'    => $parcel->service,
			'to_name'    => $to_name,
			'to_email'   => "{$settings->post_to}",
			'from_name'  => $parsed_object->from_name,
			'from_email' => $parsed_object->from_email,
			'cc'         => $parsed_object->cc,
			'bcc'        => $parsed_object->bcc,
			'subject'    => $parsed_object->subject,
			'message'    => $parsed_object->message,
			'parcel'     => $parcel
		));
	}

	public function display_settings($settings, $parcel)
	{	
		return $this->build_table($settings);
	}
}