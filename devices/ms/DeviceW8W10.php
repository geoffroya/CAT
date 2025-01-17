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
 * This file creates MS Windows 8 installers
 * It supports EAP-TLS, TTLS, PEAP and EAP-pwd
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @package ModuleWriting
 */

namespace devices\ms;
use \Exception;

/**
 *
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 * @package ModuleWriting
 */
 class DeviceW8W10 extends WindowsCommon 
 {
    final public function __construct() {
        parent::__construct();
        \core\common\Entity::intoThePotatoes();
        $this->setSupportedEapMethods(
                [
                    \core\common\EAP::EAPTYPE_TLS,
                    \core\common\EAP::EAPTYPE_PEAP_MSCHAP2,
                    \core\common\EAP::EAPTYPE_TTLS_PAP,
                    \core\common\EAP::EAPTYPE_TTLS_MSCHAP2,
                    \core\common\EAP::EAPTYPE_SILVERBULLET
                ]);
        $this->specialities['internal:use_anon_outer'][serialize(\core\common\EAP::EAPTYPE_PEAP_MSCHAP2)] = _("Anonymous identities do not use the realm as specified in the profile - it is derived from the suffix of the user's username input instead.");
        $this->specialities['media:openroaming'] = _("While OpenRoaming can be configured, it is possible that the Wi-Fi hardware does not support it; then the network definition is ignored.");
        $this->specialities['media:consortium_OI'] = _("While Passpoint networks can be configured, it is possible that the Wi-Fi hardware does not support it; then the network definition is ignored.");

        \core\common\Entity::outOfThePotatoes();
    }
    
    /**
     * create the actual installer executable
     * 
     * @return string filename of the generated installer
     *
     */    
    public function writeInstaller() {
        \core\common\Entity::intoThePotatoes();
        // create certificate files and save their names in $caFiles arrary
        $caFiles = $this->saveCertificateFiles('der');
        $this->caArray = $this->getAttribute('internal:CAs')[0];
        $outerId = $this->determineOuterIdString();
        $this->useAnon = $outerId === NULL ? FALSE : TRUE;
        $this->servers = empty($this->attributes['eap:server_name']) ? '' : implode(';', $this->attributes['eap:server_name']);
        $allSSID = $this->attributes['internal:SSID'];
        $delSSIDs = $this->attributes['internal:remove_SSID'];
        $this->prepareInstallerLang();
        $this->setGeantLink();
        $setWired = isset($this->attributes['media:wired'][0]) && $this->attributes['media:wired'][0] == 'on' ? 1 : 0;
//   create a list of profiles to be deleted after installation
        $delProfiles = [];
        foreach ($delSSIDs as $ssid => $cipher) {
            if ($cipher == 'DEL') {
                $delProfiles[] = $ssid;
            }
            if ($cipher == 'TKIP') {
                $delProfiles[] = $ssid.' (TKIP)';
            }
        }
        $windowsProfile = [];
        $eapConfig = $this->prepareEapConfig();
        $iterator = 0;
        foreach ($allSSID as $ssid => $cipher) {
            if ($cipher == 'TKIP') {
                $windowsProfile[$iterator] = $this->writeWLANprofile($ssid.' (TKIP)', $ssid, 'WPA', 'TKIP', $eapConfig, $iterator);
                $iterator++;
            }
            $windowsProfile[$iterator] = $this->writeWLANprofile($ssid, $ssid, 'WPA2', 'AES', $eapConfig, $iterator);
            $iterator++;
        }
        if ($this->device_id !== 'w8') {
            $roamingPartner = 1;
            foreach ($this->attributes['internal:consortia'] as $oneCons) {
                $knownOiName = array_search($oneCons, \config\ConfAssistant::CONSORTIUM['interworking-consortium-oi']);
                if ($knownOiName === FALSE) { // a custom RCOI as set by the IdP admin; do not use the term "eduroam" in that one!
                    $knownOiName = $this->attributes['general:instname'][0] . " "._("Roaming Partner") . " $roamingPartner";
                    $roamingPartner++;
                }
                $ssid = 'cat-passpoint-profile';
                $windowsProfile[$iterator] = $this->writeWLANprofile($knownOiName, $ssid, 'WPA2', 'AES', $eapConfig, $iterator, $oneCons);
                $iterator++;
            }
        }
        if ($setWired) {
            $this->writeLANprofile($eapConfig);
        }
        $this->loggerInstance->debug(4, "windowsProfile");
        $this->loggerInstance->debug(4, print_r($windowsProfile, true));

        $this->writeProfilesNSH($windowsProfile, $caFiles);
        $this->writeAdditionalDeletes($delProfiles);
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $this->writeClientP12File();
        }
        $this->copyFiles($this->selectedEap);
        $fedLogo = $this->attributes['fed:logo_file'] ?? NULL;
        $idpLogo = $this->attributes['internal:logo_file'] ?? NULL;
        $this->combineLogo($idpLogo, $fedLogo);
        $this->writeMainNSH($this->selectedEap, $this->attributes);
        $this->compileNSIS();
        $installerPath = $this->signInstaller();
        \core\common\Entity::outOfThePotatoes();
        return $installerPath;
    }

    private function setAuthorId() {
        if ($this->selectedEap['OUTER'] === \core\common\EAP::TTLS) {
            if ($this->useGeantLink) {
                $authorId = "67532";
            } else {
                $authorId = "311";
            }
        } else {
            $authorId = 0;
        }
        return($authorId);
    }

    private function addConsortia($oi) {
        if ($this->device_id == 'w8' || $oi == '') {
            return('');
        }
        $retval = '<Hotspot2>';
        $retval .= '<DomainName>';
        if (empty($this->attributes['internal:realm'][0])) {
            $retval .= \config\ConfAssistant::CONSORTIUM['interworking-domainname-fallback'];
        } else {
            $retval .=  $this->attributes['internal:realm'][0];
        }
        $retval .= '</DomainName>';
        $retval .= '<RoamingConsortium><OUI>' . $oi .
            '</OUI></RoamingConsortium>';
        $retval .=  '</Hotspot2>';
        return($retval);
    }
    
    private function eapConfigHeader() {
        $authorId = $this->setAuthorId();
        $profileFileCont = '<EAPConfig><EapHostConfig xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapMethod>
';
        $profileFileCont .= '<Type xmlns="http://www.microsoft.com/provisioning/EapCommon">' .
                $this->selectedEap["OUTER"] . '</Type>
<VendorId xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorId>
<VendorType xmlns="http://www.microsoft.com/provisioning/EapCommon">0</VendorType>
<AuthorId xmlns="http://www.microsoft.com/provisioning/EapCommon">' . $authorId . '</AuthorId>
</EapMethod>
';
        return($profileFileCont);
    }

    private function tlsServerValidation() {
        $profileFileCont = '
<eapTls:ServerValidation>
<eapTls:DisableUserPromptForServerValidation>true</eapTls:DisableUserPromptForServerValidation>
';
        $profileFileCont .= '<eapTls:ServerNames>' . $this->servers . '</eapTls:ServerNames>';
        foreach ($this->caArray as $certAuthority) {
            if ($certAuthority['root']) {
                $profileFileCont .= "<eapTls:TrustedRootCA>" . $certAuthority['sha1'] . "</eapTls:TrustedRootCA>\n";
            }
        }
        $profileFileCont .= '</eapTls:ServerValidation>
';
        return($profileFileCont);
    }
    
    private function msTtlsServerValidation() {
        $profileFileCont = '
        <ServerValidation>
';
        $profileFileCont .= '<ServerNames>' . $this->servers . '</ServerNames> ';
        foreach ($this->caArray as $certAuthority) {
            if ($certAuthority['root']) {
                $profileFileCont .= "<TrustedRootCAHash>" . chunk_split($certAuthority['sha1'], 2, ' ') . "</TrustedRootCAHash>\n";
            }
        }
        $profileFileCont .= '<DisablePrompt>true</DisablePrompt>
</ServerValidation>
';
        return($profileFileCont);
    }
    
    private function glTtlsServerValidation() {
        $servers = implode('</ServerName><ServerName>', $this->attributes['eap:server_name']);
        $profileFileCont = '
<ServerSideCredential>
';
        foreach ($this->caArray as $ca) {
            $profileFileCont .= '<CA><format>PEM</format><cert-data>';
            $profileFileCont .= base64_encode($ca['der']);
            $profileFileCont .= '</cert-data></CA>
';
        }
        $profileFileCont .= "<ServerName>$servers</ServerName>\n";

        $profileFileCont .= '
</ServerSideCredential>
';
        return($profileFileCont);
    }
    
    private function peapServerValidation() {
        $profileFileCont = '
        <ServerValidation>
<DisableUserPromptForServerValidation>true</DisableUserPromptForServerValidation>
<ServerNames>' . $this->servers . '</ServerNames>';
        foreach ($this->caArray as $certAuthority) {
            if ($certAuthority['root']) {
                $profileFileCont .= "<TrustedRootCA>" . $certAuthority['sha1'] . "</TrustedRootCA>\n";
            }
        }
        $profileFileCont .= '</ServerValidation>
';
        return($profileFileCont);
    }
    
    private function tlsConfig() {
        $profileFileCont = '
<Config xmlns:baseEap="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1"
  xmlns:eapTls="http://www.microsoft.com/provisioning/EapTlsConnectionPropertiesV1">
<baseEap:Eap>
<baseEap:Type>13</baseEap:Type>
<eapTls:EapType>
<eapTls:CredentialsSource>
<eapTls:CertificateStore />
</eapTls:CredentialsSource>
';    
        $profileFileCont .= $this->tlsServerValidation();
        if (\core\common\Entity::getAttributeValue($this->attributes, 'eap-specific:tls_use_other_id', 0) === 'on') {
            $profileFileCont .= '<eapTls:DifferentUsername>true</eapTls:DifferentUsername>';
            $this->tlsOtherUsername = 1;
        } else {
            $profileFileCont .= '<eapTls:DifferentUsername>false</eapTls:DifferentUsername>';
        }
        $profileFileCont .= '
</eapTls:EapType>
</baseEap:Eap>
</Config>
';
        return($profileFileCont);
    }

    private function msTtlsConfig() {        
        $profileFileCont = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EapTtls xmlns="http://www.microsoft.com/provisioning/EapTtlsConnectionPropertiesV1">
';
        $profileFileCont .= $this->msTtlsServerValidation();
        $profileFileCont .= '<Phase2Authentication>
';
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_PAP) {
            $profileFileCont .= '<PAPAuthentication /> ';
        }
        if ($this->selectedEap == \core\common\EAP::EAPTYPE_TTLS_MSCHAP2) {
            $profileFileCont .= '<MSCHAPv2Authentication>
<UseWinlogonCredentials>false</UseWinlogonCredentials>
</MSCHAPv2Authentication>
';
        }
        $profileFileCont .= '</Phase2Authentication>
<Phase1Identity>
';
        if ($this->useAnon) {
            $profileFileCont .= '<IdentityPrivacy>true</IdentityPrivacy>
';
            $profileFileCont .= '<AnonymousIdentity>' . $this->outerId . '</AnonymousIdentity>
                ';
        } else {
            $profileFileCont .= '<IdentityPrivacy>false</IdentityPrivacy>
';
        }
        $profileFileCont .= '</Phase1Identity>
</EapTtls>
</Config>
';
        return($profileFileCont);
    }
    
    private function glTtlsConfig() {        
        $profileFileCont = '
<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<EAPIdentityProviderList xmlns="urn:ietf:params:xml:ns:yang:ietf-eap-metadata">
<EAPIdentityProvider ID="' . $this->deviceUUID . '" namespace="urn:UUID">

<ProviderInfo>
<DisplayName>' . $this->translateString($this->attributes['general:instname'][0], $this->codePage) . '</DisplayName>
</ProviderInfo>
<AuthenticationMethods>
<AuthenticationMethod>
<EAPMethod>21</EAPMethod>
<ClientSideCredential>
<allow-save>true</allow-save>
';
        if ($this->useAnon) {
            if ($this->outerUser == '') {
                $profileFileCont .= '<AnonymousIdentity>@</AnonymousIdentity>';
            } else {
                $profileFileCont .= '<AnonymousIdentity>' . $this->outerId . '</AnonymousIdentity>';
            }
        }
        $profileFileCont .= '</ClientSideCredential>
';
        $profileFileCont .= $this->glTtlsServerValidation();
        $profileFileCont .= '
<InnerAuthenticationMethod>
<NonEAPAuthMethod>' . \core\common\EAP::eapDisplayName($this->selectedEap)['INNER'] . '</NonEAPAuthMethod>
</InnerAuthenticationMethod>
<VendorSpecific>
<SessionResumption>false</SessionResumption>
</VendorSpecific>
</AuthenticationMethod>
</AuthenticationMethods>
</EAPIdentityProvider>
</EAPIdentityProviderList>
</Config>
';
        return($profileFileCont);
    }

    private function peapConfig() {
        $nea = (\core\common\Entity::getAttributeValue($this->attributes, 'media:wired', 0) == 'on') ? 'true' : 'false';
        $profileFileCont = '<Config xmlns="http://www.microsoft.com/provisioning/EapHostConfig">
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>25</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV1">
';
        $profileFileCont .= $this->peapServerValidation();
        $profileFileCont .= '
<FastReconnect>true</FastReconnect>
<InnerEapOptional>false</InnerEapOptional>
<Eap xmlns="http://www.microsoft.com/provisioning/BaseEapConnectionPropertiesV1">
<Type>26</Type>
<EapType xmlns="http://www.microsoft.com/provisioning/MsChapV2ConnectionPropertiesV1">
<UseWinLogonCredentials>false</UseWinLogonCredentials>
</EapType>
</Eap>
<EnableQuarantineChecks>' . $nea . '</EnableQuarantineChecks>
<RequireCryptoBinding>false</RequireCryptoBinding>
';
        if ($this->useAnon) {
            $profileFileCont .= '<PeapExtensions>
<IdentityPrivacy xmlns="http://www.microsoft.com/provisioning/MsPeapConnectionPropertiesV2">
<EnableIdentityPrivacy>true</EnableIdentityPrivacy>
';
            if ($this->outerUser == '') {
                $profileFileCont .= '<AnonymousUserName/>
';
            } else {
                $profileFileCont .= '<AnonymousUserName>' . $this->outerUser . '</AnonymousUserName>
                ';
            }
            $profileFileCont .= '</IdentityPrivacy>
</PeapExtensions>
';
        }
        $profileFileCont .= '</EapType>
</Eap>
</Config>
';
        return($profileFileCont);
    }
    
    private function pwdConfig() {
        return('<ConfigBlob></ConfigBlob>');
    }

    /**
     * Set the GEANTLink usage flag based on device settings
     */
    private function setGeantLink() {
        if (\core\common\Entity::getAttributeValue($this->attributes, 'device-specific:geantlink', $this->device_id)[0] === 'on') {
            $this->useGeantLink = TRUE;
        }
    }

    private function prepareEapConfig() {
        if ($this->useAnon) {
            $this->outerUser = $this->attributes['internal:anon_local_value'][0];
            $this->outerId = $this->outerUser . '@' . $this->attributes['internal:realm'][0];
        }

        $profileFileCont = $this->eapConfigHeader();

        switch ($this->selectedEap['OUTER']) {
            case \core\common\EAP::TLS:
                $profileFileCont .= $this->tlsConfig();
                break;
            case \core\common\EAP::PEAP:
                $profileFileCont .= $this->peapConfig();
                break;
            case \core\common\EAP::TTLS:
                if ($this->useGeantLink) {
                    $profileFileCont .= $this->glTtlsConfig();
                } else {
                    $profileFileCont .= $this->msTtlsConfig();
                }
                break;
            case \core\common\EAP::PWD:
                $profileFileCont .= $this->pwdConfig();
                break;
            default:
                break;
        }
        return(['win' => $profileFileCont . '</EapHostConfig></EAPConfig>']);
    }

    /**
     * produce PEAP, TLS and TTLS configuration files for Windows 8
     *
     * @param string $wlanProfileName
     * @param string $ssid
     * @param string $auth can be one of "WPA", "WPA2"
     * @param string $encryption can be one of: "TKIP", "AES"
     * @param array $eapConfig XML configuration block with EAP config data
     * @param int $profileNumber counter, which profile number is this
     * @param string $oi nonempty value indicates that this is a Passpoint profile or a given OI value
     * @return string
     */
    private function writeWLANprofile($wlanProfileName, $ssid, $auth, $encryption, $eapConfig, $profileNumber, $oi = '') {
        $profileFileCont = '<?xml version="1.0"?>
<WLANProfile xmlns="http://www.microsoft.com/networking/WLAN/profile/v1">
<name>' . $wlanProfileName . '</name>
<SSIDConfig>
<SSID>
<name>' . $ssid . '</name>
</SSID>
<nonBroadcast>true</nonBroadcast>
</SSIDConfig>';
        $profileFileCont .= $this->addConsortia($oi);
        $profileFileCont .= '
<connectionType>ESS</connectionType>
<connectionMode>auto</connectionMode>
<autoSwitch>false</autoSwitch>
<MSM>
<security>
<authEncryption>
<authentication>' . $auth . '</authentication>
<encryption>' . $encryption . '</encryption>
<useOneX>true</useOneX>
</authEncryption>
';
        if ($auth == 'WPA2') {
            $profileFileCont .= '<PMKCacheMode>enabled</PMKCacheMode>
<PMKCacheTTL>720</PMKCacheTTL>
<PMKCacheSize>128</PMKCacheSize>
<preAuthMode>disabled</preAuthMode>
        ';
        }
        $profileFileCont .= '<OneX xmlns="http://www.microsoft.com/networking/OneX/v1">
<cacheUserData>true</cacheUserData>
<authMode>user</authMode>
';

        $closing = '
</OneX>
</security>
</MSM>
</WLANProfile>
';

        if (!is_dir('w8')) {
            mkdir('w8');
        }
        $xmlFname = "w8/wlan_prof-$profileNumber.xml";
        file_put_contents($xmlFname, $profileFileCont . $eapConfig['win'] . $closing);
        $this->loggerInstance->debug(2, "Installer has been written into directory $this->FPATH\n");
        $hs20 = $oi == '' ? 0 : 1;
        return("\"$wlanProfileName\" \"$encryption\" $hs20");
    }

    private function writeLANprofile($eapConfig) {
        $profileFileCont = '<?xml version="1.0"?>
<LANProfile xmlns="http://www.microsoft.com/networking/LAN/profile/v1">
<MSM>
<security>
<OneXEnforced>false</OneXEnforced>
<OneXEnabled>true</OneXEnabled>
<OneX xmlns="http://www.microsoft.com/networking/OneX/v1">
<cacheUserData>true</cacheUserData>
<authMode>user</authMode>
';
        $closing = '
</OneX>
</security>
</MSM>
</LANProfile>
';

        if (!is_dir('w8')) {
            mkdir('w8');
        }
        $xmlFname = "w8/lan_prof.xml";
        file_put_contents($xmlFname, $profileFileCont . $eapConfig['win'] . $closing);
        $this->loggerInstance->debug(2, "Installer has been written into directory $this->FPATH\n");
    }

    private function writeProfilesNSH($wlanProfiles, $caArray) {
        $this->loggerInstance->debug(4, "writeProfilesNSH");
        $this->loggerInstance->debug(4, $wlanProfiles);
        $fcontentsProfile = '';
        foreach ($wlanProfiles as $wlanProfile) {
            $fcontentsProfile .= "!insertmacro define_wlan_profile $wlanProfile\n";
        }

        file_put_contents('profiles.nsh', $fcontentsProfile);

        $fcontentsCerts = '';
        $fileHandleCerts = fopen('certs.nsh', 'w');
        if ($fileHandleCerts === FALSE) {
            throw new Exception("Unable to open new certs.nsh file for writing CAs.");
        }
        foreach ($caArray as $certAuthority) {
            $store = $certAuthority['root'] ? "root" : "ca";
            $fcontentsCerts .= '!insertmacro install_ca_cert "' . $certAuthority['file'] . '" "' . $certAuthority['sha1'] . '" "' . $store . "\"\n";
        }
        fwrite($fileHandleCerts, $fcontentsCerts);
        fclose($fileHandleCerts);
    }

    private function writeMainNSH($eap, $attr) {
        $this->loggerInstance->debug(4, "writeMainNSH");
        $this->loggerInstance->debug(4, $attr);
        $this->loggerInstance->debug(4, "Device_id = " . $this->device_id . "\n");
        $fcontents = "!define W8\n";
        if ($this->device_id == 'w10') {
            $fcontents .= "!define W10\n";
        }
        if (\config\ConfAssistant::NSIS_VERSION >= 3) {
            $fcontents .= "Unicode true\n";
        }
        $eapOptions = [
            \core\common\EAP::PEAP => ['str' => 'PEAP', 'exec' => 'user'],
            \core\common\EAP::TLS => ['str' => 'TLS', 'exec' => 'user'],
            \core\common\EAP::TTLS => ['str' => 'TTLS', 'exec' => 'user'],
            \core\common\EAP::PWD => ['str' => 'PWD', 'exec' => 'user'],
        ];
        if ($this->useGeantLink) {
            $eapOptions[\core\common\EAP::TTLS]['str'] = 'GEANTLink';
        }

// Uncomment the line below if you want this module to run under XP (only displaying a warning)
// $fcontents .= "!define ALLOW_XP\n";
// Uncomment the line below if you want this module to produce debugging messages on the client
// $fcontents .= "!define DEBUG_CAT\n";
        if ($this->tlsOtherUsername == 1) {
            $fcontents .= "!define PFX_USERNAME\n";
        }
        $execLevel = $eapOptions[$eap["OUTER"]]['exec'];
        $eapStr = $eapOptions[$eap["OUTER"]]['str'];
        if ($eap == \core\common\EAP::EAPTYPE_SILVERBULLET) {
            $fcontents .= "!define SILVERBULLET\n";
        }
        $fcontents .= '!define ' . $eapStr;
        $fcontents .= "\n" . '!define EXECLEVEL "' . $execLevel . '"';
        $fcontents .= $this->writeNsisDefines($attr);
        file_put_contents('main.nsh', $fcontents);
    }

    private function copyStandardNsi() {
        if (!$this->translateFile('eap_w8.inc', 'cat.NSI', $this->codePage)) {
            throw new Exception("Translating needed file eap_w8.inc failed!");
        }
    }

    private function copyFiles($eap) {
        $this->loggerInstance->debug(4, "copyFiles start\n");
        $this->copyBasicFiles();
        switch ($eap["OUTER"]) {
            case \core\common\EAP::TTLS:
                if ($this->useGeantLink) {
                    $this->copyGeantLinkFiles();
                } else {
                    $this->copyStandardNsi();
                }
                break;
            case \core\common\EAP::PWD:
                $this->copyPwdFiles();
                break;
            default:
                $this->copyStandardNsi();
        }
        $this->loggerInstance->debug(4, "copyFiles end\n");
        return TRUE;
    }

    private $tlsOtherUsername = 0;
    private $caArray;
    private $useAnon;
    private $servers;
    private $outerUser;
    private $outerId;
}

