<?php namespace pineapple;

class Reporting extends SystemModule
{
    public function route()
    {
        switch ($this->request->action) {
            case 'getReportConfiguration':
                $this->getReportConfiguration();
                break;

            case 'getReportContents':
                $this->getReportContents();
                break;

            case 'getEmailConfiguration':
                $this->getEmailConfiguration();
                break;

            case 'setReportConfiguration':
                $this->setReportConfiguration();
                break;

            case 'setReportContents':
                $this->setReportContents();
                break;

            case 'setEmailConfiguration':
                $this->setEmailConfiguration();
                break;

            case 'testReportConfiguration':
                $this->testReportConfiguration();
                break;
        }
    }

    private function getReportConfiguration()
    {
        $this->response = array("config" => array(
            "generateReport" => (exec("grep files/reporting /etc/crontabs/root") == "") ? false : true,
            "storeReport" => $this->uciGet("reporting.@settings[0].save_report"),
            "sendReport" => $this->uciGet("reporting.@settings[0].send_email"),
            "interval" => (string) $this->uciGet("reporting.@settings[0].interval")
        ));

        if ($this->getDevice() == "nano" && !$this->isSDAvailable()) {
            $this->response['config']['storeReport'] = false;
            $this->response['sdDisabled'] = true;
        }
    }

    private function getReportContents()
    {
        $this->response = array("config" => array(
            "pineAPLog" => $this->uciGet("reporting.@settings[0].log"),
            "clearLog" => $this->uciGet("reporting.@settings[0].clear_log"),
            "siteSurvey" => $this->uciGet("reporting.@settings[0].survey"),
            "siteSurveyDuration" => $this->uciGet("reporting.@settings[0].duration"),
            "client" => $this->uciGet("reporting.@settings[0].client"),
            "tracking" => $this->uciGet("reporting.@settings[0].tracking")
        ));
    }

    private function getEmailConfiguration()
    {
        $this->response = array("config" => array(
            "from" => $this->uciGet("reporting.@ssmtp[0].from"),
            "to" => $this->uciGet("reporting.@ssmtp[0].to"),
            "server" => $this->uciGet("reporting.@ssmtp[0].server"),
            "port" => $this->uciGet("reporting.@ssmtp[0].port"),
            "domain" => $this->uciGet("reporting.@ssmtp[0].domain"),
            "username" => $this->uciGet("reporting.@ssmtp[0].username"),
            "password" => $this->uciGet("reporting.@ssmtp[0].password"),
            "tls" => $this->uciGet("reporting.@ssmtp[0].tls"),
            "starttls" => $this->uciGet("reporting.@ssmtp[0].starttls")
        ));
    }

    private function setReportConfiguration()
    {
        $this->uciSet("reporting.@settings[0].save_report", $this->request->config->storeReport);
        $this->uciSet("reporting.@settings[0].send_email", $this->request->config->sendReport);
        $this->uciSet("reporting.@settings[0].interval", $this->request->config->interval);
        $this->response = array("success" => true);

        if ($this->request->config->generateReport === true) {
            $hours_minus_one = $this->uciGet("reporting.@settings[0].interval")-1;
            $hour_string = ($hours_minus_one == 0) ? "*" : "*/" . ($hours_minus_one + 1);
            exec("sed -i '/DO NOT TOUCH/d /\\/pineapple\\/modules\\/Reporting\\/files\\/reporting/d' /etc/crontabs/root");
            exec("echo -e '#DO NOT TOUCH BELOW\\n0 {$hour_string} * * * /pineapple/modules/Reporting/files/reporting\\n#DO NOT TOUCH ABOVE' >> /etc/crontabs/root");
            exec("/etc/init.d/cron start");
        } else {
            exec("sed -i '/DO NOT TOUCH/d /\\/pineapple\\/modules\\/Reporting\\/files\\/reporting/d' /etc/crontabs/root");
            exec("/etc/init.d/cron stop");
            exec("/etc/init.d/cron start");
        }
    }

    private function setReportContents()
    {
        $this->uciSet("reporting.@settings[0].log", $this->request->config->pineAPLog);
        $this->uciSet("reporting.@settings[0].clear_log", $this->request->config->clearLog);
        $this->uciSet("reporting.@settings[0].survey", $this->request->config->siteSurvey);
        $this->uciSet("reporting.@settings[0].duration", $this->request->config->siteSurveyDuration);
        $this->uciSet("reporting.@settings[0].client", $this->request->config->client);
        $this->uciSet("reporting.@settings[0].tracking", $this->request->config->tracking);
        $this->response = array("success" => true);
    }

    private function setEmailConfiguration()
    {
        $this->uciSet("reporting.@ssmtp[0].from", $this->request->config->from);
        $this->uciSet("reporting.@ssmtp[0].to", $this->request->config->to);
        $this->uciSet("reporting.@ssmtp[0].server", $this->request->config->server);
        $this->uciSet("reporting.@ssmtp[0].port", $this->request->config->port);
        $this->uciSet("reporting.@ssmtp[0].domain", $this->request->config->domain);
        $this->uciSet("reporting.@ssmtp[0].username", $this->request->config->username);
        $this->uciSet("reporting.@ssmtp[0].password", $this->request->config->password);
        $this->uciSet("reporting.@ssmtp[0].tls", $this->request->config->tls);
        $this->uciSet("reporting.@ssmtp[0].starttls", $this->request->config->starttls);

        file_put_contents("/etc/ssmtp/ssmtp.conf", "FromLineOverride=YES\n");
        file_put_contents("/etc/ssmtp/ssmtp.conf", "AuthUser={$this->request->config->username}\n", FILE_APPEND);
        file_put_contents("/etc/ssmtp/ssmtp.conf", "AuthPass={$this->request->config->password}\n", FILE_APPEND);
        file_put_contents("/etc/ssmtp/ssmtp.conf", "mailhub={$this->request->config->server}:{$this->request->config->port}\n", FILE_APPEND);
        file_put_contents("/etc/ssmtp/ssmtp.conf", "hostname={$this->request->config->domain}\n", FILE_APPEND);
        file_put_contents("/etc/ssmtp/ssmtp.conf", "rewriteDomain={$this->request->config->domain}\n", FILE_APPEND);
        if ($this->request->config->tls) {
            file_put_contents("/etc/ssmtp/ssmtp.conf", "UseTLS=YES\n", FILE_APPEND);
        }
        if ($this->request->config->starttls) {
            file_put_contents("/etc/ssmtp/ssmtp.conf", "UseSTARTTLS=YES\n", FILE_APPEND);
        }

        $this->response = array("success" => true);
    }

    private function testReportConfiguration()
    {
        $this->execBackground('/pineapple/modules/Reporting/files/reporting force_email');
        $this->response = array("success" => true);
    }
}
