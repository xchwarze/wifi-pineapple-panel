<?php namespace pineapple;

require_once('AccessPoint.php');
require_once('ClientMode.php');
require_once('Interfaces.php');

class Networking extends SystemModule
{
    public function route()
    {
        switch ($this->request->action) {
            case 'getRoutingTable':
                $this->getRoutingTable();
                break;

            case 'restartDNS':
                $this->restartDNS();
                break;

            case 'updateRoute':
                $this->updateRoute();
                break;

            case 'getAdvancedData':
                $this->getAdvancedData();
                break;

            case 'setHostname':
                $this->setHostname();
                break;

            case 'resetWirelessConfig':
                $this->resetWirelessConfig();
                break;

            case 'getInterfaceList':
                $this->getInterfaceList();
                break;

            case 'saveAPConfig':
                $this->saveAPConfig();
                break;

            case 'getAPConfig':
                $this->getAPConfig();
                break;

            case 'getMacData':
                $this->getMacData();
                break;

            case 'setMac':
                $this->setMac(false);
                break;

            case 'setRandomMac':
                $this->setMac(true);
                break;

            case 'resetMac':
                $this->resetMac();
                break;

            case 'scanForNetworks':
                $this->scanForNetworks();
                break;

            case 'getClientInterfaces':
                $this->getClientInterfaces();
                break;

            case 'connectToAP':
                $this->connectToAP();
                break;

            case 'checkConnection':
                $this->checkConnection();
                break;

            case 'disconnect':
                $this->disconnect();
                break;

            case 'getOUI':
                $this->getOUI();
                break;

            case 'getFirewallConfig':
                $this->getFirewallConfig();
                break;

            case 'setFirewallConfig':
                $this->setFirewallConfig();
                break;
        }
    }

    private function getRoutingTable()
    {
        exec('ifconfig | grep encap:Ethernet | awk "{print \$1}"', $routeInterfaces);
        exec('route', $routingTable);
        $routingTable = implode("\n", $routingTable);
        $this->response = array('routeTable' => $routingTable, 'routeInterfaces' => $routeInterfaces);
    }

    private function restartDNS()
    {
        $this->execBackground('/etc/init.d/dnsmasq restart');
        $this->response = array("success" => true);
    }

    private function updateRoute()
    {
        $routeInterface = escapeshellarg($this->request->routeInterface);
        $routeIP = escapeshellarg($this->request->routeIP);
        exec("route del default");
        exec("route add default gw {$routeIP} {$routeInterface}");
        $this->response = array("success" => true);
    }

    private function getAdvancedData()
    {
        exec("ifconfig -a", $ifconfig);
        $this->response = array("hostname" => gethostname(), "ifconfig" => implode("\n", $ifconfig));
    }

    private function setHostname()
    {
        exec("uci set system.@system[0].hostname=" . escapeshellarg($this->request->hostname));
        exec("uci commit system");
        exec("echo $(uci get system.@system[0].hostname) > /proc/sys/kernel/hostname");
        $this->response = array("success" => true);
    }

    private function resetWirelessConfig()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->response = $interfaceHelper->resetWirelessConfig();
    }

    private function getInterfaceList()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->response = $interfaceHelper->getInterfaceList();
    }

    private function saveAPConfig()
    {
        $accessPointHelper = new \helper\AccessPoint();
        $config = $this->request->apConfig;
        if (empty($config->openSSID) || empty($config->managementSSID)) {
            $this->error = "Error: SSIDs must be at least one character.";
            return;
        }
        if (strlen($config->managementKey) < 8 && $config->disableManagementAP == false) {
            $this->error = "Error: WPA2 Passwords must be at least 8 characters long.";
            return;
        }
        $this->response = $accessPointHelper->saveAPConfig($config);
    }

    private function getAPConfig()
    {
        $accessPointHelper = new \helper\AccessPoint();
        $this->response = $accessPointHelper->getAPConfig();
    }

    private function getMacData()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->response = $interfaceHelper->getMacData();
    }

    private function setMac($random)
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->response = $interfaceHelper->setMac($random, $this->request->interface, $this->request->mac);
    }

    private function resetMac()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->response = $interfaceHelper->resetMac($this->request->interface);
    }

    private function checkConnection()
    {
        $clientModeHelper = new \helper\ClientMode();
        $this->response = $clientModeHelper->checkConnection();
    }

    private function disconnect()
    {
        $interfaceHelper = new \helper\Interfaces();
        $clientModeHelper = new \helper\ClientMode();
        $interface = $this->request->interface;
        $uciID = $interfaceHelper->getUciID($interface);
        $radioID = $interfaceHelper->getRadioID($interface);
        $this->response = $clientModeHelper->disconnect($uciID, $radioID);
    }

    private function connectToAP()
    {
        $interfaceHelper = new \helper\Interfaces();
        $clientModeHelper = new \helper\ClientMode();
        $interface = $this->request->interface;
        $uciID = $interfaceHelper->getUciID($interface);
        $radioID = $interfaceHelper->getRadioID($interface);
        $this->response = $clientModeHelper->connectToAP($uciID, $this->request->ap, $this->request->key, $radioID);
    }

    private function scanForNetworks()
    {
        $interfaceHelper = new \helper\Interfaces();
        $clientModeHelper = new \helper\ClientMode();
        $interface = $this->request->interface;
        $uciID = $interfaceHelper->getUciID($interface);
        $radioID = $interfaceHelper->getRadioID($interface);
        $this->response = $clientModeHelper->scanForNetworks($interface, $uciID, $radioID);
    }

    private function getClientInterfaces()
    {
        $interfaceHelper = new \helper\Interfaces();
        $this->response = $interfaceHelper->getClientInterfaces();
    }

    private function getOUI()
    {
        $data = file_get_contents("https://www.wifipineapple.com/oui.txt");
        if ($data !== null) {
            $this->response = array("ouiText" => implode("\n", $data));
        } else {
            $this->error = "Failed to download OUI file from WiFiPineapple.com";
        }
    }

    private function getFirewallConfig()
    {
        $this->response = array("allowWANSSH" => $this->uciGet("firewall.allowssh.enabled"),
                                "allowWANUI" => $this->uciGet("firewall.allowui.enabled"));
    }

    private function setFirewallConfig()
    {
        $wan = $this->request->WANSSHAccess ? 1 : 0;
        $ui = $this->request->WANUIAccess ? 1 : 0;
        $this->uciSet("firewall.allowssh.enabled", $wan);
        $this->uciSet("firewall.allowui.enabled", $ui);
        $this->uciSet("firewall.allowws.enabled", $ui);
        exec('/etc/init.d/firewall restart');

        $this->response = array("success" => true);
    }
}
