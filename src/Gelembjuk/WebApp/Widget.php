<?php

namespace Gelembjuk\WebApp;

abstract class Widget extends AppClass {
    protected $inputdata = [];
    protected $viewdata = [];
    protected $displayFormat = 'html';

    abstract protected function getTemplateName();
    abstract protected function parseInput($params);
    abstract protected function prepareData();

	public function render($params)
    {
        try {
            $this->parseInput($params);
        } catch (\Exception $e) {
            return $this->outputError($e->getMessage());
        }
        
        $this->prepareData();

        if ($this->displayFormat == 'json') {
            return json_encode($this->viewdata);
        }

        // else disp;ay as HTML 
        $htmlTemplate = $this->getTemplateName();

        return $this->renderTemplate($htmlTemplate);
    }
    protected function outputError($message)
    {
        return $message;
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
