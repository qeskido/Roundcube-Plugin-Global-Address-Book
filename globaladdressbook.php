<?php

/**
 * GlobalAddressbook
 *
 * Plugin to add a global address book
 *
 * @version 1.3
 * @author Philip Weir
 */
class globaladdressbook extends rcube_plugin
{
	public $task = 'mail|addressbook';
	private $abook_id = 'global';
	private $readonly;
	private $groups;
	private $user_id;
	private $user_name;
	private $host = 'localhost';

	public function init()
	{
		$rcmail = rcmail::get_instance();
		$this->load_config();
		$this->user_name = $rcmail->config->get('globaladdressbook_user');
		$user_info = explode('@', $_SESSION['username']);
		if (count($user_info) >= 2)
			$this->user_name = str_replace('%d', $user_info[1], $this->user_name);
		$this->user_name = str_replace('%h', $_SESSION['imap_host'], $this->user_name);
		$this->readonly = $this->_is_readonly();
		$this->groups = $rcmail->config->get('globaladdressbook_groups', false);

		// check if the global address book user exists
		if (!($user = rcube_user::query($this->user_name, $this->host)))
			$user = rcube_user::create($this->user_name, $this->host);

		$this->user_id = $user->ID;

		// use this address book for autocompletion queries
		if ($rcmail->config->get('globaladdressbook_autocomplete')) {
			$sources = $rcmail->config->get('autocomplete_addressbooks', array('sql'));
			if (!in_array($this->abook_id, $sources)) {
				$sources[] = $this->abook_id;
				$rcmail->config->set('autocomplete_addressbooks', $sources);
			}
		}

		$this->add_hook('address_sources', array($this, 'address_sources'));
		$this->add_hook('get_address_book', array($this, 'get_address_book'));
	}

	public function address_sources($args)
	{
		$this->add_texts('localization/');
		$args['sources'][$this->abook_id] = array('id' => $this->abook_id, 'name' => $this->gettext('globaladdressbook'), 'readonly' => $this->readonly, 'groups' => $this->groups);
		return $args;
	}

	public function get_address_book($args)
	{
		if ($args['id'] === $this->abook_id) {
			$args['instance'] = new rcube_contacts(rcmail::get_instance()->db, $this->user_id);
			$args['instance']->readonly = $this->readonly;
			$args['instance']->groups = $this->groups;
		}

		return $args;
	}

	private function _is_readonly() {
		$rcmail = rcmail::get_instance();

		if (!$rcmail->config->get('globaladdressbook_readonly'))
			return false;

		if ($admin = $rcmail->config->get('globaladdressbook_admin')) {
			if (!is_array($admin)) $admin = array($admin);

			foreach ($admin as $user) {
				if (strpos($user, '/') == 0 && substr($user, -1) == '/') {
					if (preg_match($user, $_SESSION['username']))
						return false;
				}
				elseif ($user == $_SESSION['username'])
					return false;
			}
		}

		return true;
	}
}

?>