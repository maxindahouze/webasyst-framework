<?php

/*
 * This file is part of Webasyst framework.
 *
 * Licensed under the terms of the GNU Lesser General Public License (LGPL).
 * http://www.webasyst.com/framework/license/
 *
 * @link http://www.webasyst.com/
 * @author Webasyst LLC
 * @copyright 2011 Webasyst LLC
 * @package wa-system
 * @subpackage controller
 */
class waDefaultViewController extends waViewController
{
    protected $action;

    public function setAction($action)
    {
        if ($action instanceof waViewAction) {
            $action->setController($this);
        }
        $this->action = $action;
    }

    public function execute()
    {
        if (!$this->action instanceof waViewAction) {
            $class_name = $this->action;
            $this->action = new $class_name();
        }

        if (!$this->layout && $this->action && $this->action->getLayout()) {
            $this->setLayout($this->action->getLayout());
        }

        $this->executeAction($this->action);
    }
}
