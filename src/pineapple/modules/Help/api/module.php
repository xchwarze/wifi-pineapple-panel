<?php namespace pineapple;

class Help extends SystemModule
{
    public function route()
    {
        switch ($this->request->action) {
            case 'generateDebugFile':
                $this->generateDebugFile();
                break;

            case 'downloadDebugFile':
                $this->downloadDebugFile();
                break;

            case 'getConsoleOutput':
                $this->getConsoleOutput();
                break;
        }
    }

    private function generateDebugFile()
    {
        @unlink('/tmp/debug.log');
        $this->execBackground("(/pineapple/modules/Help/files/debug 2>&1) > /tmp/debug_generation_output");
        $this->response = array("success" => true);
    }

    private function downloadDebugFile()
    {
        if (!file_exists('/tmp/debug.log')) {
            $this->error = "The debug file is missing.";
            return;
        }
        $this->response = array("success" => true, "downloadToken" => $this->downloadFile("/tmp/debug.log"));
    }

    private function getConsoleOutput()
    {
        $output = "";
        if (file_exists("/tmp/debug_generation_output")) {
            $output = file_get_contents("/tmp/debug_generation_output");
        }
        $this->response = array("output" => $output);
    }
}
