<?php

/*
	This is a controller to handle a few of the general functions for the Property entity.
	In this framework, the `methodnameSetup` function is called before the HTTP method-specific
	method gets called. `setup` gets called as a constructor for the entire controller.
*/

namespace APP\Controller\Account;

class Properties extends \APP\Controller\Account
{

	public function setup()
	{
		parent::setup();

		if ($this->user->status() == 'suspended') {
			$this->redirect('/account');
		}

		$this->active_menu_item = 'properties';
	}

	public function indexGET()
	{
		$this->redirect('/account');
	}

	public function newGET()
	{
		if ($this->user->is_delinquent()) {
			$this->flash("<strong>Heads-up!</strong> We're having trouble collecting payment on your account. Please update your payment method below before adding a new property.", 'danger');
			$this->redirect('/account/profile');
		}

		// insert property
		$property = \APP\Model::Property()->create();

		$property->user_id($this->user->id());
		$property->guid(uniqid());
		$property->status('draft');

		$property->save();

		$count = \APP\Model::Property()->query('SELECT COUNT(*) as count FROM redacted WHERE user_id = ? AND status != ?', array($this->user->id(), 'deleted'))[0]['count'];
		
		if ($count == 1) {
			$onboarding = "/first_property";
		}

		// redirect
		$this->redirect('/account/properties/edit/'.$property->guid().$onboarding);
	}

	public function editGET()
	{
		$this->property = \APP\Model::Property()->find('guid = ? AND user_id = ? AND status != ?', array($this->param('guid'), $this->user->id(), 'deleted'));

		if (!$this->property) {
			$this->flash("That property couldn't be found.", 'danger');
			$this->redirect('/account');
		}

		$this->page_title = "Property Editor";
		$this->show_onboarding = $this->param('show_onboarding', 'false');
	}

	public function unpublishSetup()
	{
		$this->property = \APP\Model::Property()->find('guid = ? AND user_id = ? AND status = ?', array($this->param('guid'), $this->user->id(), 'active'));

		if (!$this->property) {
			$this->flash("That property couldn't be found.", 'danger');
			$this->redirect('/account');
		}

		$this->page_title = "Unpublish Property";
	}

	public function unpublishGET()
	{
		//
	}

	public function unpublishPOST()
	{
		if (!$this->request->post['password']) {
			$this->errors = "Please enter your account password.";
		}

		if (!password_verify($this->request->post['password'], $this->user->password())) {
			$this->errors = "The password you entered doesn't match our records.";
			return;
		}

		$result = $this->property->unpublish();

		if (!$result['success']) {
			$this->errors = $result['error'];
			return;
		}

		$this->flash("The property site for ".$this->property->address_line1()." has been successfully unpublished.", 'success');
		$this->redirect('/account');
	}

	public function deleteSetup()
	{
		$this->property = \APP\Model::Property()->find('guid = ? AND user_id = ? AND status = ?', array($this->param('guid'), $this->user->id(), 'draft'));

		if (!$this->property) {
			$this->flash("That property couldn't be found.", 'danger');
			$this->redirect('/account');
		}

		$this->page_title = "Delete Property";
	}

	public function deleteGET()
	{
		//
	}

	public function deletePOST()
	{
		$this->property->status('deleted');
		$this->property->deleted(date(REDACTED_TIME_FORMAT));

		$this->property->save();

		$this->flash("The draft has been deleted.", 'success');
		$this->redirect('/account');
	}

}

?>
