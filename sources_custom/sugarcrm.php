<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2017

 See text/EN/licence.txt for full licencing information.

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    sugarcrm
 */

// Built using this library: https://github.com/asakusuma/SugarCRM-REST-API-Wrapper-Class
// Dev guide: http://support.sugarcrm.com/Documentation/Sugar_Developer/Sugar_Developer_Guide_6.5/Application_Framework/Web_Services/

function init__sugarcrm()
{
    global $SUGARCRM;
    $SUGARCRM = null;

    require_code('sugar_crm_lib');
    require_code('curl');
    require_lang('sugarcrm');

    $base_url = get_option('sugarcrm_base_url');
    $username = get_option('sugarcrm_username');
    $password = get_option('sugarcrm_password');

    if ((empty($base_url)) || (empty($username))) {
        return;
    }

    $SUGARCRM = new SugarWrapper;

    $SUGARCRM->setUrl($base_url . '/service/v2/rest.php');
    $SUGARCRM->setUsername($username);
    $SUGARCRM->setPassword($password);

    try {
        if (!$SUGARCRM->connect()) {
            sugarcrm_failed('Could not connect to SugarCRM');
        }
    }
    catch (Exception $e) {
        sugarcrm_failed($e->getMessage());
    }
}

function sugarcrm_failed($message)
{
    global $SUGARCRM;
    $SUGARCRM = null;

    if (php_function_allowed('error_log')) {
        error_log('SugarCRM issue: ' . $message, 0);
    }
    require_code('failure');
    relay_error_notification(false, $message);
}

function get_or_create_sugarcrm_account($company, $timestamp = null)
{
    global $SUGARCRM;

    if ($timestamp === null) {
        $timestamp = time();
    }

    $response = $SUGARCRM->get(
        'Accounts',
        array('id'),
        array(
            'where' => 'name=\'' . db_escape_string($company) . '\'',
        )
    );
    if (isset($response[0])) {
        $account_id = $response[0];
    } else {
        $response = $SUGARCRM->set(
            'Accounts',
            array(
                array('name' => 'name', 'value' => $company),
                array('name' => 'date_entered', 'value' => timestamp_to_sugarcrm_date_string($timestamp)),
            )
        );
        $account_id = $response['id'];
    }
    return $account_id;
}

function get_sugarcrm_contact($email_address, $account_id = null)
{
    global $SUGARCRM;

    //$where = 'email1=\'' . db_escape_string($email_address) . '\'';   Not queryable
    $where = "contacts.id IN (SELECT bean_id FROM email_addr_bean_rel eabr JOIN email_addresses ea ON (eabr.email_address_id = ea.id) WHERE bean_module = 'Contacts' AND ea.email_address='" . db_escape_string($email_address) . "' AND eabr.deleted=0)";

    $response = $SUGARCRM->get(
        'Contacts',
        array('id', 'account_id', 'name', 'account_name'),
        array(
            'where' => $where,
        )
    );

    if ($account_id !== null) {
        // We have to do with filtering
        foreach ($response as $contact_details) {
            if ($contact_details['account_id'] === $account_id) {
                if (!isset($contact_details['account_name'])) {
                    $contact_details['account_name'] = $contact_details['name']; // For older versions of SugarCRM
                }

                return $contact_details;
            }
        }
        return null;
    }

    if (isset($response[0])) {
        // Return first result
        $contact_details = $response[0];
        return $contact_details;
    }

    return null;
}

function deconstruct_long_name($username)
{
    $username = str_replace(array('_', '-'), array(' ', ' '), $username);

    if (strpos($username, ' ') === false) {
        $matches = array();
        $name_parts = array();
        $num_matches = preg_match_all('#([A-Z]+[^A-Z]*)#i', $username, $matches);
        for ($i = 0; $i < $num_matches; $i++) {
            $name_parts[] = $matches[1][$i];
        }
    } else {
        $name_parts = explode(' ', $username);
    }

    $last_name = cms_mb_ucwords(trim(array_pop($name_parts), '0123456789'));
    $first_name = cms_mb_ucwords(implode(' ', $name_parts));

    return array($first_name, $last_name);
}

function timestamp_to_sugarcrm_date_string($timestamp)
{
    return date('Y-m-d H:i:s', $timestamp);
}

function save_message_into_sugarcrm_as_configured($subject, $body, $from_email, $from_name, $attachments, $data, $posted_data, $timestamp = null)
{
    $sync_type = post_param_string('sugarcrm_messaging_sync_type', get_option('sugarcrm_messaging_sync_type'));
    $messaging_mappings = explode("\n", get_option('sugarcrm_messaging_mappings'));
    return save_message_into_sugarcrm($sync_type, $messaging_mappings, $subject, $body, $from_email, $from_name, $attachments, $data, $posted_data, $timestamp);
}

function save_composr_account_into_sugarcrm_as_configured($member_id, $timestamp = null)
{
    $username = $GLOBALS['FORUM_DRIVER']->get_username($member_id);
    $email_address = $GLOBALS['FORUM_DRIVER']->get_member_email_address($member_id);

    require_code('cns_members');
    $_cpfs = cns_get_all_custom_fields_match_member($member_id);
    $cpfs = array();
    foreach ($_cpfs as $cpf_title => $cpf) {
        if ($cpf_title != do_lang('cns:SMART_TOPIC_NOTIFICATION')) {
            $cpfs[$cpf_title] = $cpf['RAW'];
        }
    }

    $posted_data = $_POST + $_GET + $_COOKIE;

    $member_mappings = explode("\n", get_option('sugarcrm_member_mappings'));

    $sync_types = get_option('sugarcrm_member_sync_types');

    $contact_id = null;

    if (in_array($sync_types, array('leads', 'leads_guarded', 'both', 'both_guarded'))) {
        // User metadata
        $attachments = array();
        require_code('lookup');
        $user_metadata_path = save_user_metadata();
        $attachments[$user_metadata_path] = 'user_metadata.txt';

        $body = do_lang('BODY_LEAD_FROM_ACCOUNT', $username);
        $data = array(do_lang('AUTOMATIC_NOTE') => $body) + $cpfs;

        save_message_into_sugarcrm('leads', $member_mappings, '', $body, $email_address, $username, $attachments, $data, $posted_data, $timestamp, strpos($sync_types, '_guarded') !== false);
    }

    if (in_array($sync_types, array('contacts', 'both', 'both_guarded'))) {
        $contact_id = save_account_into_sugarcrm($member_mappings, $username, null, null, $email_address, $cpfs, $posted_data, $timestamp);
    }

    return $contact_id;
}

function save_message_into_sugarcrm($sync_type, $mappings, $subject, $body, $from_email, $from_name, $attachments, $data, $posted_data, $timestamp = null, $guarded = false)
{
    /*
    Notes...

    When posting to SugarCRM API, SugarCRM won't do required-field validation.
    SugarCRM is basically a data dump at the low-level.
    Unrecognised values are silently-skipped.
    */

    global $SUGARCRM;

    if ($timestamp === null) {
        $timestamp = time();
    }

    $_sync_type = ucfirst($sync_type);

    $lead_source = do_lang('DEFAULT_LEAD_SOURCE');

    // Metadata
    $data_extended = $data;
    foreach ($attachments as $file_path => $filename) {
        if ($filename == 'user_metadata.txt') {
            $metadata = json_decode(file_get_contents($file_path), true);
            foreach ($metadata as $key => $val) {
                if (is_array($val)) {
                    if (!isset($val[0])) { // Not a list
                        $data_extended += $val;
                    }
                } else {
                    $data_extended[$key] = $val;
                }
            }
        }
    }

    // Find Contact (no auto-creation, will link to contact manually set up in SugarCRM or from a joined member - by binding to e-mail address as a key - or just won't find one which is fine)
    $contact_details = get_sugarcrm_contact($from_email);

    // Find company name
    if (empty($data_extended['company'])) {
        if ($contact_details === null) {
            $company = get_option('sugarcrm_default_company');
        } else {
            $company = $contact_details['account_name'];
        }
    } else {
        $company = $data_extended['company'];
    }
    unset($data['company']);

    // Name fields
    list($first_name, $last_name) = deconstruct_long_name($from_name);

    // Create Case/Lead
    $sugarcrm_data = array(
        'status' => array('name' => 'status', 'value' => 'New'),
    );
    switch ($sync_type) {
        case 'cases':
            // Find/create Account
            if ($contact_details === null) {
                $account_id = get_or_create_sugarcrm_account($company, $timestamp);
            } else {
                $account_id = $contact_details['account_id'];
            }

            $sugarcrm_data += array(
                // These are for Case-only
                'account_id' => array('name' => 'account_id', 'value' => $account_id),
                'name' => array('name' => 'name', 'value' => or_unknown(($subject == '') ? $from_name : $subject)),
                'description' => array('name' => 'description', 'value' => $body),
                'priority' => array('name' => 'priority', 'value' => 'P2'),
                'date_entered' => array('name' => 'date_entered', 'value' => timestamp_to_sugarcrm_date_string($timestamp)),
            );
            break;

        case 'leads':
            $sugarcrm_data += array(
                // These are for Lead-only
                'account_name' => array('name' => 'account_name', 'value' => $company), // We don't use actual accounts for Leads, just a company name in a field named account_name
                'description' => array('name' => 'description', 'value' => $subject),
                'date_entered' => array('name' => 'date_entered', 'value' => timestamp_to_sugarcrm_date_string($timestamp)),

                'name' => array('name' => 'name', 'value' => or_unknown($from_name)), // SuiteCRM
                'first_name' => array('name' => 'first_name', 'value' => $first_name),
                'last_name' => array('name' => 'last_name', 'value' => or_unknown($last_name)),

                'email1' => array('name' => 'email1', 'value' => $from_email),

                'lead_source' => array('name' => 'lead_source', 'value' => $lead_source),
            );
            break;
    }
    foreach ($mappings as $_mapping) {
        if (strpos($_mapping, '=') !== false) {
            list($mapping_from, $mapping_to) = array_map('trim', explode('=', $_mapping, 2));

            $matches = array();
            if (preg_match('#^\((.*)\)$#', $mapping_from, $matches) != 0) {
                $value = $matches[1];
            } elseif (preg_match('#^\[(.*)\]$#', $mapping_from, $matches) != 0) {
                $value = isset($posted_data[$matches[1]]) ? $posted_data[$matches[1]] : '';
            } else {
                $value = isset($data_extended[$mapping_from]) ? $data_extended[$mapping_from] : '';
                unset($data[$mapping_from]);
            }

            if ($value == '') {
                continue;
            }

            if ((isset($sugarcrm_data[$mapping_to])) && ($sugarcrm_data[$mapping_to]['value'] != '') && (!in_array($mapping_to, array('date_entered', 'priority', 'name', 'first_name', 'last_name', 'email1', 'lead_source')))) {
                if ($value != '') {
                    $label = post_param_string('label_for__' . $mapping_from, '');
                    if ($label != '') {
                        $value = $label . ': ' . $value;
                    }
                    $sugarcrm_data[$mapping_to]['value'] .= "\n\n" . $value;
                }
            } else {
                $sugarcrm_data[$mapping_to] = array('name' => $mapping_to, 'value' => $value);
            }
        }
    }
    if ($sync_type == 'leads') {
        // Any remaining fields should not be lost (for Cases though we put it all into 'description', as a case looks more like an e-mail, with a subject line and body)
        foreach ($data as $mapping_from => $value) {
            if ($value == '') {
                continue;
            }

            $label = post_param_string('label_for__' . $mapping_from, $mapping_from);
            if ((isset($sugarcrm_data['description'])) && ($sugarcrm_data['description']['value'] != '')) {
                $sugarcrm_data['description']['value'] .= "\n\n" . $label . ': ' . $value;
            } else {
                $sugarcrm_data['description'] = array('name' => 'description', 'value' => $label . ': ' . $value);
            }
        }

        // If there's an existing lead with this e-mail then put an addendum on the description to mention that
        /*$existing_leads = $SUGARCRM->search_by_module(        Opens too many files
            $from_email,
            array('Leads'),
            0,
            -1
        );
        $num_existing_leads = count($existing_leads['entry_list'][0]['records']);*/
        $existing_leads = $SUGARCRM->get(
            'Leads',
            array('id'),
            array(
                'where' => 'leads.id in (SELECT eabr.bean_id FROM email_addr_bean_rel eabr JOIN email_addresses ea ON (ea.id = eabr.email_address_id) WHERE eabr.deleted=0 AND ea.email_address = \'' . db_escape_string($from_email) . '\')',
            )
        );
        $num_existing_leads = count($existing_leads);
        if ($num_existing_leads > 0) {
            if ($guarded) {
                return false;
            }

            $sugarcrm_data['description']['value'] .= "\n\n" . do_lang('EXISTING_LEADS', integer_format($num_existing_leads));
        }
    }
    $response = $SUGARCRM->set(
        $_sync_type,
        array_values($sugarcrm_data)
    );
    $entity_id = $response['id'];

    // Create Contact underneath Case (for Lead it is part of the main set of Lead fields)
    if (($sync_type == 'cases') && ($last_name != '')) {
        $sugarcrm_data = array(
            'account_id' => array('name' => 'account_id', 'value' => $account_id),
            'account_name' => array('name' => 'account_name', 'value' => $company),
            'first_name' => array('name' => 'first_name', 'value' => $first_name),
            'last_name' => array('name' => 'last_name', 'value' => $last_name),

            'emailAddress0' => array('name' => 'emailAddress0', 'value' => $from_email), // SuiteCRM
            'email1' => array('name' => 'email1', 'value' => $from_email),
        );
        $response = $SUGARCRM->set(
            'Contacts',
            array_values($sugarcrm_data)
        );
        $contact_id = $response['id'];
        $SUGARCRM->set_relationship($_sync_type, $entity_id, 'contacts', array($contact_id));
    }

    // Create Notes under Lead
    if ($sync_type == 'leads') {
        foreach ($attachments as $file_path => $filename) {
            $sugarcrm_data = array(
                'name' => array('name' => 'name', 'value' => $filename),
                'description' => array('name' => 'description', 'value' => ''),
                'parent_type' => array('name' => 'parent_type', 'value' => 'Leads'),
                'parent_id' => array('name' => 'parent_id', 'value' => $entity_id),
            );
            if ($contact_details !== null) {
                $sugarcrm_data['contact_id'] = array('name' => 'contact_id', 'value' => $contact_details['id']);
            }
            $response = $SUGARCRM->set(
                'Notes',
                array_values($sugarcrm_data)
            );
            $note_id = $response['id'];
            $SUGARCRM->set_note_attachment($note_id, base64_encode(file_get_contents($file_path)), $filename);
        }
    }

    // Create Documents underneath Case
    if ($sync_type == 'cases') {
        foreach ($attachments as $file_path => $filename) {
            $sugarcrm_data = array(
                'document_name' => array('name' => 'document_name', 'value' => $filename),
                'revision' => array('name' => 'revision', 'value' => '1'),
            );
            $response = $SUGARCRM->set(
                'Documents',
                array_values($sugarcrm_data)
            );
            $document_id = $response['id'];
            $SUGARCRM->set_document_revision($document_id, $filename, $file_path, '1');
            $SUGARCRM->set_relationship($_sync_type, $entity_id, 'documents', array($document_id));
        }
    }

    return true;
}

function save_account_into_sugarcrm($mappings, $username, $first_name, $last_name, $email_address, $data, $posted_data, $timestamp = null)
{
    global $SUGARCRM;

    if ($timestamp === null) {
        $timestamp = time();
    }

    $lead_source = do_lang('DEFAULT_LEAD_SOURCE');

    $company_field = get_option('sugarcrm_composr_company_field');

    $company = isset($data[$company_field]) ? $data[$company_field] : get_option('sugarcrm_default_company');

    if ($username === null) {
        $username = $first_name . ' ' . $last_name;
    } else {
        list($_first_name, $_last_name) = deconstruct_long_name($username);
        if ($first_name === null) {
            $first_name = $_first_name;
        }
        if ($last_name === null) {
            $last_name = $_last_name;
        }
    }

    // Find/create Account
    $account_id = get_or_create_sugarcrm_account($company, $timestamp);

    // Find/create Contact
    $contact_details = get_sugarcrm_contact($email_address, $account_id);
    if ($contact_details === null) {
        $sugarcrm_data = array(
            'account_id' => array('name' => 'account_id', 'value' => $account_id),
            'date_entered' => array('name' => 'date_entered', 'value' => timestamp_to_sugarcrm_date_string($timestamp)),

            'name' => array('name' => 'name', 'value' => $username), // SuiteCRM
            'first_name' => array('name' => 'first_name', 'value' => $first_name),
            'last_name' => array('name' => 'last_name', 'value' => $last_name),

            'emailAddress0' => array('name' => 'emailAddress0', 'value' => $email_address), // SuiteCRM
            'email1' => array('name' => 'email1', 'value' => $email_address),

            'lead_source' => array('name' => 'lead_source', 'value' => $lead_source),
        );

        foreach ($mappings as $_mapping) {
            if (strpos($_mapping, '=') !== false) {
                list($mapping_from, $mapping_to) = array_map('trim', explode('=', $_mapping, 2));

                $matches = array();
                if (preg_match('#^\((.*)\)$#', $mapping_from, $matches) != 0) {
                    $value = $matches[1];
                } elseif (preg_match('#^\[(.*)\]$#', $mapping_from, $matches) != 0) {
                    $value = isset($posted_data[$matches[1]]) ? $posted_data[$matches[1]] : '';
                } else {
                    $value = isset($data[$mapping_from]) ? $data[$mapping_from] : '';
                }

                if ($value == '') {
                    continue;
                }

                $sugarcrm_data[$mapping_to] = array('name' => $mapping_to, 'value' => $value);
            }
        }

        $response = $SUGARCRM->set(
            'Contacts',
            array_values($sugarcrm_data)
        );
        $contact_id = $response['id'];
    } else {
        $contact_id = $contact_details['id'];
    }
    return $contact_id;
}

function or_unknown($str)
{
    return empty($str) ? do_lang('UNKNOWN') : $str;
}
