<?php

class Banner extends DataObject {

	static $db = array(
		'Title' => 'Varchar(255)',
		'ImageAlt' => 'Varchar(255)'
	);

	static $has_one = array (
		'BannerGroup' => 'BannerGroup',
		'Image' => 'BetterImage',
	);

	/**
	 * @return FieldSet
	 */
	public function getCMSFields($params = null) {
		$fields = FormUtils::createMain();
		$fields->addFieldToTab('Root.Main', $field = new TextField('Title'));
		$fields->addFieldToTab('Root.Main', $field = new TextField('ImageAlt', 'Image alt text'));
		$fields->addFieldToTab('Root.Main', $field = new ImageUploadField('Image'));
		UploadFolderManager::setUploadFolder($this, $field);
		if( $this->hasField('LinkTargetURL') ) {
			LinkFields::addLinkFields($fields, null, 'Root.Link');
		}
		return $fields;
	}

	public static function add_link_fields() {
		DataObject::add_extension('Banner', 'LinkFieldsDecorator');
	}

	public function forTemplate() {
		return $this->Image()->forTemplate();
	}

	protected function onAfterWrite() {
		parent::onAfterWrite();
		if( ($image = $this->Image()) && ($image->exists()) ) {
			if( $this->ImageAlt )
				$image->Title = $this->ImageAlt;
			elseif( $this->Title )
				$image->Title = $this->Title;
			else
				$image->Title = '';
			$image->write();
		}
	}

}

UploadFolderManager::setOptions('Banner', array('subsite' => false));