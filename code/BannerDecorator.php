<?php
/**
 * Use BannerDecorator::add_extension() in order to be able to use UploadFolderManager::printUploadFolders().
 * @author simonwade
 */
class BannerDecorator extends DataObjectDecorator {

	public static $restrictToGroup;
	protected static $tabName;
	protected static $inheritFromParent = true;

	public static function add_extension( $className ) {
		Object::add_extension($className, 'BannerDecorator');
		if( Director::isDev() && class_exists('UploadFolderManager') ) {
			UploadFolderManager::setOptions($className, array());
		}
	}

	/**
	 * Restricts the selection of banners to a single BannerGroup in the CMS fields
	 * @param BannerGroup $group May be a BannerGroup or its ID
	 */
	public static function restrictToGroup( $group ) {
		if( !$group )
			self::$restrictToGroup = null;
		else
			self::$restrictToGroup = 
				( is_object($group) ? $group
					: ( is_string($group) ? BannerGroup::get_by_identifier($group)
						: DataObject::get_by_id('BannerGroup', $group) ) );
	}

	/**
	 * Restricts the selection of banners to a single BannerGroup in the CMS fields
	 * @param BannerGroup $group May be a BannerGroup or its ID
	 */
	public static function setTabName( $tabName ) {
		self::$tabName = $tabName;
	}

	public static function getTabName( $owner, $fields ) {
		if( isset(self::$tabName) ) {
			$tabName = self::$tabName;
		}
		else {
			$tabName = $owner instanceof Page ? 'Root.Content.Images' : 'Root.Images';
		}
		return $tabName;
	}

	public static function setInheritFromParent( $bool = true ) {
		self::$inheritFromParent = $bool;
	}

	public function extraStatics() {
		return array(
			'db' => array(
				'BannerType' => 'Enum("None,Image,SingleBanner,BannerGroup")',
				'BannerCarousel' => 'Int',
			),
			'has_one' => array(
				'BannerImage' => 'BetterImage',
				'SingleBanner' => 'Banner',
				'BannerGroup' => 'BannerGroup',
			),
			'defaults' => array(
				'BannerType' => 'None'
			)
		);
	}

	public function updateCMSFields( FieldSet $fields ) {
		Requirements::css('banners/css/BannerDecorator.css');
		$filter = self::$restrictToGroup ? "BannerGroupID = '".self::$restrictToGroup->ID."'" : '';
		if( $banners = DataObject::get('Banner', $filter) ) { /* @var $banners DataObjectSet */
			$banners = $banners->map();
		}
		else {
			$banners = array('-- No banners avaialable --');
		}
		$filter = self::$restrictToGroup ? "ID = '".self::$restrictToGroup->ID."'" : '';
		if( $bannerGroups = DataObject::get('BannerGroup', $filter) ) { /* @var $bannerGroups DataObjectSet */
			$bannerGroups = $bannerGroups->map();
		}
		else {
			$bannerGroups = array('-- No banner groups available --');
		}
		$tabName = $this->getTabName($this->owner, $fields);
		$fields->addFieldToTab($tabName, $field = new LiteralField('BannerImage', '<h3>Banner Image</h3>'.NL));
		$options = array();
		$options['None//No custom banner'] = new LiteralField(null, '');
		$options['BannerGroup//Banner group'] = new CompositeField(array(
			new DropdownField('BannerGroupID', '', $bannerGroups),
			new CheckboxField('BannerCarousel', 'Display the banners in a scrolling image carousel'),
		));

		$options['SingleBanner//Single banner'] = new DropdownField('SingleBannerID', '', $banners);
		$options['Image//Upload an Image'] = $upload = new ImageUploadField('BannerImage', '');
		$banner = new SelectionGroup('BannerType', $options);
		if( class_exists('UploadFolderManager') )
			UploadFolderManager::setUploadFolder($this->owner, $upload, 'Banners');
		else
			$upload->setUploadFolder('Uploads/Banners');
		
		$fields->addFieldToTab($tabName, $banner);
	}

	public function Banner() {
		$rv = false;
		switch( $this->owner->BannerType ) {
			case 'BannerGroup':
				if( $group = $this->owner->BannerGroup() ) { /* @var $group BannerGroup */
					$rv = $group->RandomBanner();
				}
				break;
			case 'Image':
				$rv = new Banner();
				$rv->Image = $this->owner->BannerImage();
				break;
			case 'None':
				if( self::$inheritFromParent && $this->owner->hasMethod('Subsite') ) {
					$subsite = $this->owner->Subsite();
					if( $subsite->hasMethod('Banner') )
						return $subsite->Banner();
				}
				break;
			case 'SingleBanner':
				$rv = $this->owner->SingleBanner();
				break;
		}
		if( !$rv || !$rv->Image()->fileExists() ) {
			if( self::$inheritFromParent && $this->owner->Parent ) {
				$rv = $this->owner->Parent->Banner();
			}
		}
		return $rv ? $rv : new Banner();
	}

	public function HasBanner() {
		return $this->Banner()->Image()->fileExists() ? true : false;
	}

	public function HasCarousel() {
		if( $this->owner->BannerType == 'BannerGroup' && $this->owner->BannerCarousel )
			return ($banners = $this->AllBanners()) && ($banners->exists()) && ($banners->Count() > 1);
		return false;
	}

	public function BannerLink( $width, $height ) {
		return $this->BannerURL($width, $height);
	}

	public function BannerURL( $width = null, $height = null ) {
		$image = $this->owner->Banner()->Image();
		if( $image->exists() && file_exists($image->getFullPath()) ) {
			if( $height && $width )
				$image = $image->setResizedSize($width, $height);
			else if( $width && !$height )
				$image = $image->SetWidth($width);
			else if( !$width && $height )
				$image = $image->SetHeight($height);
			$this->resizedImage = $image;
			return $image->Filename;
		}
	}

	public function BannerHeight() {
		return isset($this->resizedImage) ? $this->resizedImage->getHeight() : $this->Banner()->Image()->getHeight();
	}

	public function BannerWidth() {
		return isset($this->resizedImage) ? $this->resizedImage->getWidth() : $this->Banner()->Image()->getWidth();
	}

	public function BannerCSS( $width, $height ) {
		if( $url = $this->BannerURL($width, $height) ) {
			return "background-image: url(".htmlspecialchars($url).")";
		}
	}

	public function AllBanners() {
		$set = $rv = false;

		switch( $this->owner->BannerType ) {
			case 'BannerGroup':
				if( $group = $this->owner->BannerGroup() )
					$set = $group->Banners(null, 'SortOrder ASC');
				break;
			case 'Image':
				$image = $this->owner->BannerImage();
				break;
			case 'SingleBanner':
				$image = $this->owner->SingleBanner();
				break;
		}

		if( isset($image) && $image->fileExists() )
			$set = new DataObjectSet(array($image));

		if( (!$set || !$set->Count()) && self::$inheritFromParent ) {
			if( $this->owner->Parent )
				$set = $this->owner->Parent->AllBanners();

			if( !$set && $this->owner->hasMethod('Subsite') )
				$set = $this->owner->Subsite()->AllBanners();
		}

		return $set;
	}

	public function BannerMarkup( $width = null, $height = null, $transform = 'SetCroppedSize' ) {
		$bannerType = $this->owner->BannerType;
		//unsure how null values got in the DB, 
		//but this aughta hold the little S.O.B:
		if (!$bannerType) $bannerType = 'None';
		
		$bannerCarousel = $this->owner->BannerCarousel;
		
		if( $bannerType == 'None' && self::$inheritFromParent && $this->owner->hasMethod('Subsite') ) {
			$subsite = $this->owner->Subsite();
			$bannerType = $subsite->BannerType;
			$bannerCarousel = $subsite->BannerCarousel;
		}

		if( $bannerType == 'BannerGroup' && $bannerCarousel ) {
			$items = new DataObjectSet();
			if( $allBanners = $this->AllBanners() ) {
				foreach( $allBanners as $banner ) {
					$item = new ImageCarouselItem();
					$item->initFromDataObject($banner);
					$items->push($item);
				}
			}
			if( $items->Count() > 1 ) {
				$carousel = new SlidesCarousel($items);
				$carousel->$transform($width, $height);
				return $carousel;
			}
		}

		if( $banner = $this->Banner() ) {
			$image = $banner->Image()->$transform($width, $height);
			if( strpos(get_class($image), 'Image') !== false ) {
				if( $banner->hasMethod('LinkURL') ) {
					return ($banner->LinkURL() ? '<a href="' . $banner->LinkURL() . '">' : '') .
						$image->getTag() .
						($banner->LinkURL() ? '</a>' : '');
				}
				else {
					return $image->getTag();
				}
				
			}
		}
		return null;
	}

	public static function removeBannerFields( FieldSet $fields, DataObject $owner ) {
		$fields->removeByName('BannerImage');
		$fields->removeByName('BannerType');
		$tabName = self::getTabName($owner, $fields);
		if( ($tab = $fields->fieldByName($tabName)) && ($tab instanceof Tab) ) { /* @var $tab Tab */
			if( $tab && ($tab->Fields()->Count() == 0) ) {
				$fields->removeByName(preg_replace('/.+\./', '', $tabName));
			}
		}
	}

}
