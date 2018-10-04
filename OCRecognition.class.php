<?php 
require __DIR__ . '/vendor/autoload.php';

Class OCRecognition {
	private static $apiKey = "bbc50be49f88957";

	public $maxWidth = 1280;
	public $maxHeight = 720;

	public $rawData = '';
	public $image = "";
	public $fileExtension = "";
	private $tmpName = "";
	public $apiUpload = '';
	public $email = '';
	public $phoneNumber = '';
	public $website = '';
	public $unmatched = '';


	public function getData($file, $upload_dir){
		$this->getTheRawData($file, $upload_dir);
		return $this->processData();
	}

	public function getTheRawData($file, $upload_dir)
	{
		$file = $this->isUpload($file);
		if($this->isImage($file)){
			$this->createRandomName();
			$this->resizeImage($upload_dir);
			$this->setRawData($this->uploadToApi());
			return $this->getRawData();
		}
	}

	public function isUpload($file){
		if(is_array($file)){
			print_r(move_uploaded_file($file['tmp_name'], 'uploads/tmp/tmp.jpg'));

			$file = "uploads/tmp/tmp.jpg";
		}

		return $file;
	}

	public function isImage($file) 
	{
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$fileType = finfo_file($finfo, $file);


		if(preg_match("^image\W^", $fileType)){
			$this->setImage($file);
			$this->setFileExtension($file);
			return true;
		}

		return false;
	}

	public function resizeImage($upload_dir)
	{
		$image = new \Gumlet\ImageResize($this->getImage());
		$image->resizeToBestFit($this->getMaxWidth(), $this->getMaxHeight());
		$image->save($upload_dir . "/" . $this->getName() . "." . $this->getFileExtension());
		$this->setApiUpload($upload_dir . "/" . $this->getName() . "." . $this->getFileExtension());
	}

	public function createRandomName($length=10){
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	    $charactersLength = strlen($characters);
	    $randomName = '';
	    for ($i = 0; $i < $length; $i++) {
	        $randomName .= $characters[rand(0, $charactersLength - 1)];
	    }

	    $this->setName($randomName);
	}

	public function uploadToApi()
	{
	  	$fileData = fopen($this->getApiUpload(), 'r');
	  	//$fileData = file_get_contents($this->getApiUpload());
	  	//$base64 = base64_encode($fileData);
	  	//print_r($imagedata);

	    $client = new \GuzzleHttp\Client();
	    
	    try {
	    $r = $client->request('POST', 'https://api.ocr.space/parse/image',[
	        'headers' => ['apiKey' => self::$apiKey],
	        'language' => 'dut',
	        'detectOrientation' => 'true',
	        'multipart' => [
	            [
	                'name' => 'file',
	                'contents' => $fileData
	            ]
	        ]
	    ], ['file' => $fileData]);
	    

	    	$response =  json_decode($r->getBody(),true);
	    	$output = "";
	    	if(array_key_exists('ParsedResults', $response)){
	    		 foreach($response['ParsedResults'] as $pareValue) {
                    $output.= $pareValue['ParsedText'];
                }

                return $output;
	    	}
	    } catch (Exception $e){
	    	throw new Exception($e->getMessage());
	    }
	}

	public function processData(){
		$this->processMail();
		$this->processPhoneNumber();
		$this->processWebsite();
		$this->processUnmatched();

		return $this->getUserdata();

	}

	public function processMail() {
		$rawData = $this->getRawData();
		$mailPattern = "/[-0-9a-zA-Z.+_]+@[-0-9a-zA-Z.+_]+.[a-zA-Z]{2,9}/";
		$mailMatches = [];
		preg_match_all($mailPattern, $rawData, $mailMatches);
		if(count(array_filter($mailMatches)) > 0) $this->setEmail($mailMatches[0][0]);
	}

	public function processPhoneNumber(){
		$rawData = $this->getRawData();
		$phonePattern = "/((\+|00(\s|\s?\-\s?)?)31(\s|\s?\-\s?)?(\(0\)[\-\s]?)?|0)[1-9]((\s|\s?\-\s?)?[0-9])((\s|\s?-\s?)?[0-9])((\s|\s?-\s?)?[0-9])\s?[0-9]\s?[0-9]\s?[0-9]\s?[0-9]\s?[0-9]/";
		$phoneMatches = [];
		preg_match_all($phonePattern, $rawData, $phoneMatches);
		if(count(array_filter($phoneMatches)) > 0) $this->setPhoneNumber($phoneMatches[0][0]);
	}

	public function processWebsite(){
		$rawData = $this->getRawData();
		$mail = $this->getEmail();
		$unmatched = str_replace($mail, "", $rawData);

		$websitePattern = "/(https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9][a-zA-Z0-9-]+[a-zA-Z0-9]\.[^\s]{2,}|https?:\/\/(?:www\.|(?!www))[a-zA-Z0-9]\.[^\s]{2,}|www\.[a-zA-Z0-9]\.[^\s]{2,})|([a-zA-Z]*\.[^\s]{2,})/";
		$websiteMatches= [];
		preg_match_all($websitePattern, $unmatched, $websiteMatches);
		if(count(array_filter($websiteMatches)) > 0) $this->setWebsite($websiteMatches[0][0]);
	}

	public function processUnmatched(){
		$rawData = $this->getRawData();
		$mail = $this->getEmail();
		$phoneNumber = $this->getPhoneNumber();
		$website = $this->getWebsite();

		$unmatched = str_replace($mail, "", $rawData);
		$unmatched = str_replace($phoneNumber, "", $unmatched);
		$unmatched = str_replace($website, "", $unmatched);

		$this->setUnmatched($unmatched);

	}

	public function getUserData(){
		$userData = array(
			'email' => $this->getEmail(),
			'phone' => $this->getPhoneNumber(),
			'website' => $this->getWebsite(),
			'noMatches' => $this->getUnmatched()
		);

		return $userData;
	}

	public function setMaxWidth($maxWidth)
	{
		$this->maxWidth = $maxWidth;
	}

	public function getMaxWidth()
	{
		return $this->maxWidth;
	}

	public function setMaxHeight($maxHeight)
	{
		$this->maxHeight = $maxHeight;
	}

	public function getMaxHeight()
	{
		return $this->maxHeight;
	}

	public function setImage($image)
	{
		$this->image = $image;
	}

	public function getImage()
	{
		return $this->image;
	}

	public function setApiUpload($file)
	{
		$this->apiUpload = $file;
	}

	public function getApiUpload()
	{
		return $this->apiUpload;
	}

	public function setFileExtension($file)
	{
		$extension = pathinfo($file)['extension'];
		$this->fileExtension = $extension;
	}

	public function getFileExtension()
	{
		return $this->fileExtension;
	}

	public function setRawData($data){
		$this->rawData = $data;
	}

	public function getRawData()
	{
		return $this->rawData;
	}

	public function setName($name)
	{
		$this->tmpName = $name;
	}

	public function getName()
	{
		return $this->tmpName;
	}

	public function setEmail($email)
	{
		$this->email = $email;
	}

	public function getEmail()
	{
		return $this->email;	
	}

	public function setPhoneNumber($phoneNumber)
	{
		$this->phoneNumber = $phoneNumber;
	}

	public function getPhoneNumber()
	{
		return $this->phoneNumber;	
	}

	public function setWebsite($website)
	{
		$this->website = $website;
	}

	public function getWebsite()
	{
		return $this->website;	
	}

	public function setUnmatched($data)
	{
		$this->unmatched = $data;
	}	

	public function getUnmatched()
	{
		return $this->unmatched;
	}

}