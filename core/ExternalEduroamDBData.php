<?php

/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

/**
 * This file contains the AbstractProfile class. It contains common methods for
 * both RADIUS/EAP profiles and SilverBullet profiles
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package Developer
 *
 */

namespace core;

use \Exception;

/**
 * This class interacts with the external DB to fetch operational data for
 * read-only purposes.
 * 
 * @author Stefan Winter <stefan.winter@restena.lu>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class ExternalEduroamDBData extends EntityWithDBProperties {

    private $SPList = [];

    /**
     * constructor, gives us access to the DB handle we need for queries
     */
    public function __construct() {
        $this->databaseType = "EXTERNAL";
        parent::__construct();
    }

    /**
     * we don't write anything to the external DB, so no need to track update
     * timestamps
     * 
     * @return void
     */
    public function updateFreshness() {
        
    }

    /**
     * eduroam DB delivers a string with all name variants mangled in one. Pry
     * it apart.
     * 
     * @param string $nameRaw the string with all name variants coerced into one
     * @return array language/name pair
     */
    private function splitNames($nameRaw) {
        // the delimiter used by eduroam DB is ; but that is ALSO an allowed
        // character in payload, and in active use. We need to try and find out
        // which semicolon should NOT be considered a language delimiter...
        $cleanName = preg_replace('/;\[/', '##DBLIMIT##[', $nameRaw);
        $variants = explode('##DBLIMIT##', $cleanName);
        $submatches = [];
        $returnArray = [];
        foreach ($variants as $oneVariant) {
            if ($oneVariant == NULL) {
                return [];
            }
            if (!preg_match('/^\[(.*)\]\ (.*)/', $oneVariant, $submatches) || !isset($submatches[2])) {
                throw new Exception("We expect '[CC] bla but found '$oneVariant'.");
            }
            $returnArray[$submatches[1]] = $submatches[2];
        }
        return $returnArray;
    }

    /**
     * retrieves the list of all service providers from the eduroam database
     * 
     * @return array list of providers
     */
    public function allServiceProviders() {
        if (count($this->SPList) == 0) {
            $query = $this->databaseHandle->exec("SELECT country, inst_name, sp_location FROM view_active_SP_location_eduroamdb");
            while ($iterator = mysqli_fetch_object(/** @scrutinizer ignore-type */ $query)) {
                $this->SPList[] = ["country" => $iterator->country, "instnames" => $this->splitNames($iterator->inst_name), "locnames" => $this->splitNames($iterator->sp_location)];
            }
        }
        return $this->SPList;
    }

}