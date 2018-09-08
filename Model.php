<?php

/*
	This is a model for a relatively complex set of data within an application. It encapsulates
	the majority of actions that can be taken on this given entity, as well as provides related
	entities.
*/

namespace APP\Model\Row;

class Property extends \APP\Model\Row
{
	// Relations ////////////////////////////

	public function photos($force_refresh=false)
	{
		if (!isset($this->photos) || $force_refresh) {
			$this->photos = \APP\Model::Photo()->findAll('property_guid = ? AND status = ? ORDER BY sort_order', array($this->guid(), 'active'));
		}

		return $this->photos;
	}

	public function pages()
	{
		if (!isset($this->pages)) {
			$this->pages = \APP\Model::Page()->findAll('property_guid = ? AND status = ? ORDER BY created', array($this->guid(), 'active'));
		}

		return $this->pages;
	}

	public function details()
	{
		if (!isset($this->details)) {
			$this->details = \APP\Model::Detail()->findAll('property_guid = ? AND status = ? ORDER BY sort_order', array($this->guid(), 'active'));
		}

		return $this->details;
	}

	public function agent()
	{
		if (!isset($this->agent)) {
			$this->agent = \APP\Model::Agent()->find('guid = ?', $this->agent_guid());
		}

		return $this->agent;
	}

	public function user()
	{
		if (!isset($this->user)) {
			$this->user = \APP\Model::User()->find($this->user_id());
		}

		return $this->user;
	}

	public function leads()
	{
		if (!isset($this->leads)) {
			$this->leads = \APP\Model::Lead()->findAll('property_guid = ? AND status = ? ORDER BY id DESC', array($this->guid(), 'active'));
		}

		return $this->leads;
	}

	// Properties ////////////////////////////

	public function address()
	{
		if (!$this->address_line1()) {
			return "No Address";
		}

		return $this->address_line1().", ".($this->address_line2() ? $this->address_line2().", " : '').$this->address_city().", ".$this->address_state()." ".$this->address_zip();
	}

	public function address_short()
	{
		if (!$this->address_line1()) {
			return "<span class='text-muted'>No Address</span>";
		}

		$output = $this->address_line1();

		if ($this->address_zip()) {
			$output .= ", ".$this->address_zip();
		}

		return $output;
	}

	public function getThemeOptions()
	{
		return json_decode($this->theme_options(), true);
	}

	public function primary_photo()
	{
		if (!$this->primary_photo_guid()) {
			return false;
		}

		if (!isset($this->primary_photo)) {
			$this->primary_photo = \APP\Model::Photo()->find('guid = ?', $this->primary_photo_guid());
		}

		return $this->primary_photo;
	}

	// return array of featured photos, sorted by their sort order
	public function featured_photos()
	{
		if (!isset($this->featured_photos)) {
			$this->featured_photos = array();

			$photos = $this->photos();

			if ($photos) {
				foreach ($photos as $photo) {
					if ($photo->feature_sort_order() != null) {
						$this->featured_photos[$photo->feature_sort_order()] = $photo;
					}
				}
			}

			ksort($this->featured_photos);
		}

		return $this->featured_photos;
	}

	public function random_photos($number)
	{
		if (!isset($this->random_photos)) {
			$this->random_photos = $this->photos();

			shuffle($this->random_photos);
		}

		return array_slice($this->random_photos, 0, $number);
	}

	public function url()
	{
		if (!$this->domain() || !$this->is_live()) {
			return \APP\Util\RedactedUtility::root_url();
		}

		return "http://www.".$this->domain();
	}

	public function getStatus()
	{
		if ($this->status() == 'active') {
			return "<span class='text-success'><i class='fa fa-check-circle'></i> Active</span>";
		}

		if ($this->status() == 'pending') {
			return "<i class='fa fa-clock-o'></i> Pending</span>";
		}

		return ucfirst($this->status());
	}

	// Utility ////////////////////////////

	public function geocode()
	{
		$geocode = json_decode(file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($this->address()).'&key='.getenv('GOOGLE_MAPS_KEY')), true);

		if (count($geocode['results'])) {
			$this->address_lat($geocode['results'][0]['geometry']['location']['lat']);
			$this->address_lng($geocode['results'][0]['geometry']['location']['lng']);

			$this->save();
		}
	}

	public function geocoded()
	{
		return ($this->address_lat() && $this->address_lng());
	}

	/*
		For this particular platform, I generally have a rawOutput() method that simply exports
		data that would be later suitable to output via API endpoints in JSON format.
		This way, I know exactly what data is being publicized.
	*/
	public function rawOutput()
	{
		// pages
		$pages = array();

		if (count($this->pages())) {
			foreach ($this->pages() as $page) {
				$pages[] = $page->rawOutput();
			}
		}

		// photos
		$photos = array();

		if (count($this->photos())) {
			foreach ($this->photos() as $photo) {
				$photos[] = $photo->rawOutput();
			}
		}

		// detail
		$details = array();

		if (count($this->details())) {
			foreach ($this->details() as $detail) {
				$details[] = $detail->rawOutput();
			}
		}

		// base data
		$output = array(
			'guid'               => $this->guid(),
			'agent_guid'         => $this->agent_guid(),
			'address_line1'      => $this->address_line1(),
			'address_line2'      => $this->address_line2(),
			'address_city'       => $this->address_city(),
			'address_state'      => $this->address_state(),
			'address_zip'        => $this->address_zip(),
			'price'              => $this->price(),
			'intro_text'         => $this->intro_text(),
			'details_text'       => $this->details_text(),
			'theme'              => $this->theme(),
			'theme_options'      => $this->getThemeOptions(),
			'mls'                => $this->mls(),
			'is_sold'            => $this->is_sold(),
			'is_live'            => intval($this->is_live()),
			'status'             => $this->status(),
			'primary_photo_guid' => $this->primary_photo_guid(),
			'photos'             => $photos,
			'pages'              => $pages,
			'details'            => $details,
		);

		return $output;
	}

	public function jsonOutput()
	{
		return json_encode($this->rawOutput());
	}

	// render the entire site to a local output folder
	public function render_site($is_preview=false)
	{
		$this->geocode();

		$salt = time();

		// assemble list of pages
		$pages_to_render = \APP\Util\RedactedUtility::$default_pages;

		if (count($this->pages())) {
			foreach ($this->pages() as $custom_page) {
				$pages_to_render[] = $custom_page->guid();
			}
		}

		// cURL each page
		foreach ($pages_to_render as $page) {
			$curl = curl_init(); 

			/*
				Redacted. This is a cURL request to an endpoint which will render this
				individual page by spinning up the applicable template view, capturing the HTML
				output, and saving it to a file.
			*/

			$output = curl_exec($curl);

			curl_close($curl);

			$json = json_decode($output, true);

			if (!$json) {
				return array('success' => 0, 'message' => "An unexpected issue occurred when preparing this preview.", 'raw' => $output);
			}
			
			if (isset($json['error_message'])) {
				return array('success' => 0, 'message' => $json['error_message']);
			}
		}

		$this->preview_generated(date(REDACTED_TIME_FORMAT));
		$this->save();

		// place key file
		file_put_contents(\APP\Util\RedactedUtility::$properties_tmp_folder."/".$this->guid()."_".$salt."/site_test_".$this->guid().".key", $this->preview_generated());

		return array('success' => 1, 'output_folder' => \APP\Util\RedactedUtility::$properties_tmp_folder."/".$this->guid()."_".$salt);
	}

	// render a single page
	public function render_page($page, $tmp_folder_suffix, $is_preview=false)
	{
		// set up temporary folder and miscellaneous variables
		$tmp_folder = \APP\Util\RedactedUtility::$properties_tmp_folder;

		if (!file_exists($tmp_folder)) {
			mkdir($tmp_folder);
		}

		$tmp_folder .= "/".$this->guid()."_".$tmp_folder_suffix;

		if (!file_exists($tmp_folder)) {
			mkdir($tmp_folder);
		}

		$output_subfolder = null;

		// set up the controller instance
		$view = new \RPC\Controller;
		$view->property = $this;
		$view->page = $page;

		// if the page is a custom page, adjust some things accordingly
		if (is_object($page)) {
			$output_subfolder = $page->slug()."/";
			$output_layout = '_static';
		} else {
			if ($page != 'index' && $page != '404') {
				$output_subfolder = $page."/";
			}

			$output_layout = $page;
		}

		$view->current_page = $output_layout;
		$view->is_preview = $is_preview;

		// run the view
		ob_start();
		$view->display('property/modern/'.$output_layout.'.php');

		// make the output directory, if needed
		if ($output_subfolder) {
			mkdir($tmp_folder."/".$output_subfolder);
		}
		
		// save
		if ($page == '404') {
			$destination_file = $tmp_folder."/".$output_subfolder."404.html";
		} else {
			$destination_file = $tmp_folder."/".$output_subfolder."index.html";
		}

		file_put_contents($destination_file, ob_get_contents());
		ob_end_clean();

		return $destination_file;
	}

	public function publish()
	{
		// render site
		$render = $this->render_site();

		if (!$render['success']) {
			return $render;
		}

		// upload to S3
		$s3_folder_prefix = null;

		if (\APP\Util\RedactedUtility::development_mode()) {
			$s3_folder_prefix = "_test_site/";
			$bucket = getenv('AMAZON_S3_BUCKET');
			$url_to_check = "http://cdn.Redacted.com.s3-website-us-east-1.amazonaws.com/_test_site";
		} else {
			$bucket = 'www.'.$this->domain();
			$url_to_check = "http://".$bucket;
		}

		$directory_index = new \RecursiveDirectoryIterator($render['output_folder']);
		
		foreach (new \RecursiveIteratorIterator($directory_index) as $filename => $file) {
			if (basename($filename) == '.' || basename($filename) == '..') {
				continue;
			}

			$s3_folder = rtrim(str_replace(array($render['output_folder']."/", basename($filename)), '', $filename), '/');

		    \APP\Util\S3::upload($filename, '', '', \APP\Util\S3::ACL_PUBLIC_READ, $s3_folder_prefix.$s3_folder, $bucket);
		}

		// if it's active, set is_live and has_unpublished_changes accordingly
		if ($this->status() == 'active') {
			$this->is_live(1);
			$this->has_unpublished_changes(0);

			$this->save();
		}

		return array('success' => 1, 'url' => $url_to_check);
	}

	public function unpublish()
	{
		// delete files from bucket
		if (\APP\Util\RedactedUtility::development_mode()) {
			$bucket = redacted;
		} else {
			$bucket = redacted;
		}

		// list files
		$files = \APP\Util\S3::doGetBucket($bucket);

		if ($files) {
			foreach ($files as $file) {
				// delete file
				\APP\Util\S3::doDeleteObject($bucket, $file['name']);
			}
		}

		// place redirect
		$tmp_folder = \APP\Util\RedactedUtility::$properties_tmp_folder;

		if (!file_exists($tmp_folder)) {
			mkdir($tmp_folder);
		}

		$redirect_file = $tmp_folder."/index.html";

		file_put_contents($redirect_file, "<html><head><title>Redacted</title></head><body><script>window.location='".\APP\Util\RedactedUtility::root_url('site-inactive')."';</script></body></html>");
		\APP\Util\S3::upload($redirect_file, '', '', \APP\Util\S3::ACL_PUBLIC_READ, '', $bucket);

		// update property
		$this->is_live(0);
		$this->status('deleted');
		$this->deleted(date(REDACTED_TIME_FORMAT));

		$this->save();

		// update subscription
		$result = $this->user()->update_subscription(-1, $this);

		// return
		return $result;
	}
}

?>