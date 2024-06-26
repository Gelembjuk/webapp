<?php

namespace Gelembjuk\WebApp;

abstract class Widget extends AppClass {
    protected $inputdata = [];
    protected $viewdata = [];
    protected $displayFormat = 'html';
    protected $router = null;

    abstract protected function getTemplateName();
    abstract protected function prepareData();

	public function render($params = [])
    {
        $this->inputdata = $params;

        try {
            $this->prepareData();

        } catch (\Exception $e) {
            return $this->outputError($e->getMessage());
        }
        
        if ($this->displayFormat == 'json') {
            return json_encode($this->viewdata);
        }

        // else disp;ay as HTML 
        $htmlTemplate = $this->getTemplateName();

        return $this->renderTemplate($htmlTemplate);
    }
    public function withRouter($router)
    {
        $this->router = $router;

        return $this;
    }
    public function withResponseFormat($format)
    {
        $this->displayFormat = $format;

        return $this;
    }
    protected function getInput($key, $type = 'string', $default = null)
    {
        if ($this->router) {
            return $this->router->getInput($key, $type);
        }
        return $this->inputdata[$key] ?? $default;
    }
    protected function outputError($message)
    {
        if ($this->displayFormat == 'json') {
            return json_encode(['error' => $message]);
        }
        return '<span class="color:red;font-weight:bold;">'.$message.'</span>';
    }
    private function renderTemplate($template)
    {
        $options = $this->application->getOption('htmltemplatesoptions');

        if (!is_array($options)) {
            $options = [];
        }

        $options['templatepath'] = $this->application->getOption('htmltemplatespath');
        
        $processorClass = '\\Gelembjuk\\Templating\\SmartyTemplating';

        if (!empty($options['templatingclass'])) {
			$processorClass = $options['templatingclass'];
		}
        
        $templating = new $processorClass();
		
		$templating->init($options);

		$templating->setApplication($this->application);

        if (!$templating->checkTemplateExists($template)) {
            return sprintf('Template %s not found', $template);
        }
        $templating->setTemplate($template);

        $templating->setVars($this->viewdata);
		
		return $templating->fetchTemplate();
    }
}
