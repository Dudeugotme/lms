<?php

class LocationCache {

    // database connection instance
	private $DB                   = null;

    // cache array with key as LMS database ID
    private $city_by_id           = array();
    private $city_by_id_loaded    = false;

    // cache array with key as city ident
    private $city_by_ident        = array();
    private $city_by_ident_loaded = false;

    // location buildings cache
    private $buildings            = array();

    // location street cache
    private $streets              = array();
    private $streets_loaded       = false;

    // load policy types
    const LOAD_FULL = 'full';
    const LOAD_ONE  = 'one';

    // choosen load policy
    private $load_policy = self::LOAD_ONE;

    /*!
     * \brief Class constructor.
     *
     * \param string load policy
     */
	public function __construct( $load_policy = null ) {
		$this->DB = LMSDB::getInstance();

		if ( $load_policy !== null ) {
			$this->setLoadPolicy( $load_policy );
		}
	}

	/*!
	 * \brief Set loading policy.
	 *
	 * \param $v
	 * LOAD_FULL 'full':
	 * After first get method use will be downloaded all rows from table and
	 * save to array. All next asks about will be returned from cache.
	 * Use this option if you need to read many rows and your database is't
	 * big.
	 *
	 * LOAD_ONE 'one':
	 * Similar to REAL_TIME but remember only last searched value in cache.
	 * Use this option when you need to read many rows but get full table in
	 * one time can exceed a memory limit.
	 */
	public function setLoadPolicy( $v ) {
		$v = trim( $v );
		$v = strtolower( $v );

		switch ( $v ) {
			case self::LOAD_FULL:
			case self::LOAD_ONE:
				$this->load_policy = $v;
			break;

			default:
				throw new Exception('Illegal state exception. Incorrect load policy value.');
		}
	}

	/*!
	 * \brief Return row from location_cities by id.
	 * equals to:
	 * SELECT * FROM location_cities WHERE id = x;
	 *
	 * \param int   $id row id
	 * \param array if record was found
	 * \param null  if record wasn't found
	 */
	public function getCityById( $id ) {

		switch ( $this->load_policy ) {

			case self::LOAD_FULL:
				if ( $this->city_by_id_loaded == false ) {
					$this->initCityByIdCache();
				}

			    if ( isset( $this->city_by_id[ $id ] ) ) {
			        return $this->city_by_id[ $id ];
			    } else {
			    	return null;
			    }
			break;

			case self::LOAD_ONE:
				if ( isset( $this->city_by_id[ $id ] ) ) {
			        return $this->city_by_id[ $id ];
			    } else {
			    	$this->city_by_id = $this->DB->GetAllByKey('SELECT id, ident, cityid FROM location_cities WHERE id = ?', 'id', array( $id ));

			    	if ( isset( $this->city_by_id[ $id ] ) ) {
			        	return $this->city_by_id[ $id ];
			    	}

			    	return null;
			    }
			break;
		}
	}

	/*!
	 * \brief Return row from location_cities by id.
	 * equals to:
	 * SELECT * FROM location_cities WHERE ident like 'x';
	 *
	 * \param int   $ident row ident
	 * \param array if record was found
	 * \param null  if record wasn't found
	 */
	public function getCityByIdent( $ident ) {

	    switch ( $this->load_policy ) {
			case self::LOAD_FULL:
				if ( $this->city_by_ident_loaded == false ) {
					$this->initCityByIdentCache();
				}

			    if ( isset( $this->city_by_ident[ $ident ] ) ) {
			        return $this->city_by_ident[ $ident ];
			    } else {
			    	return null;
			    }
			break;

			case self::LOAD_ONE:
				if ( isset( $this->city_by_ident[ $ident ] ) ) {
			        return $this->city_by_ident[ $ident ];
			    } else {
			    	$this->city_by_ident = $this->DB->GetAllByKey('SELECT id, ident, cityid FROM location_cities WHERE ident = ?', 'ident', array( (string) $ident ));

			    	if ( isset( $this->city_by_ident[ $ident ] ) ) {
			        	return $this->city_by_ident[ $ident ];
			    	} else {
				    	return null;
				    }
			    }
			break;
		}
	}

	/*!
	 * \brief Return row from location streets by ident.
	 * Equals to:
	 * SELECT * FROM location_streets WHERE ident like 'x';
	 *
	 * \param $ident row ident number
	 * \param array  if record was found
	 * \param null   if record wasn't found
	 */
	public function getStreetByIdent( $cityid, $ident ) {
	    switch ( $this->load_policy ) {
	    	case self::LOAD_FULL:
				if ( $this->streets_loaded == false ) {
					$this->streets = $this->DB->getAllByKey('SELECT id, ident FROM location_streets', 'ident');
					$this->streets_loaded = true;
				}

				if ( isset($this->streets[$ident]) ) {
					return $this->streets[$ident];
				} else {
					return null;
				}
	    	break;

	        case self::LOAD_ONE:
	        	if ( !isset($this->streets[ $ident ]) ) {
					$this->streets = $this->DB->getAllByKey('SELECT id, ident FROM location_streets WHERE cityid = ?', 'ident', array( $cityid ));
				}

				if ( isset($this->streets[$ident]) ) {
					return $this->streets[$ident];
				} else {
					return null;
				}
		    break;
		}
	}

	/*!
	 * \brief Check if building exists in database.
	 *
	 * \param $cityid
	 * \param $streetid
	 * \param $building_num
	 * \return array with building fields
	 * \return false if not found
	 */
	public function buildingExists( $cityid, $streetid, $building_num ) {
		if ( !isset($this->buildings[ $cityid ]) ) {
			$tmp = $this->DB->GetAllByKey("SELECT (city_id || '|' || case when street_id is null then '' else '' || street_id end || '|' || building_num) as lms_building_key,
											longitude, latitude, id
											FROM location_buildings lb
											WHERE city_id = ?", 'lms_building_key', array($cityid));

			$this->buildings = null;
			$this->buildings[ $cityid ] = $tmp;
		}

		$key = $cityid . '|' . $streetid . '|' . $building_num;

	    if ( isset($this->buildings[ $cityid ][ $key ]) ) {
	    	return $this->buildings[ $cityid ][ $key ];
	    } else {
	    	return false;
	    }
	}

    /*
     * \brief Create cache array or try imitate from other cache file.
     */
    private function initCityByIdentCache() {

    	if ( $this->city_by_id_loaded == true ) {
            foreach ($this->city_by_id as $v) {
                $this->city_by_ident[ $v['ident'] ] = $v;
            }
		} else {
			$this->city_by_ident = $this->DB->GetAllByKey('SELECT id, ident, cityid FROM location_cities;', 'ident');
		}

    	$this->city_by_ident_loaded = true;
    }

    /*
     * \brief Create cache array or try imitate from other cache file.
     */
    private function initCityByIdCache() {

    	if ( $this->city_by_ident_loaded == true ) {
            foreach ($this->city_by_ident as $v) {
                $this->city_by_id[ $v['id'] ] = $v;
            }
		} else {
			$this->city_by_id = $this->DB->GetAllByKey('SELECT id, ident, cityid FROM location_cities;', 'id');
		}

    	$this->city_by_id_loaded = true;
    }
}

?>
