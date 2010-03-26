  protected function getPager()
  {
    $query = $this->buildQuery();
    $paginateMethod = $this->configuration->getPaginateMethod();
    $pager = $query->$paginateMethod($this->getPage(), $this->configuration->getPagerMaxPerPage());

    return $pager;
  }

  protected function setPage($page)
  {
    $this->getUser()->setAttribute('<?php echo $this->getModuleName() ?>.page', $page, 'admin_module');
  }

  protected function getPage()
  {
    return $this->getUser()->getAttribute('<?php echo $this->getModuleName() ?>.page', 1, 'admin_module');
  }

  protected function buildQuery()
  {
<?php if ($this->configuration->hasFilterForm()): ?>
    if (null === $this->filters)
    {
      $this->filters = $this->configuration->getFilterForm($this->getFilters());
      foreach ($this->configuration->getFieldsFilter() as $key => $field)
      {
       if (isset($field['widget']))
       {
         $widget = $field['widget'];
         $options = (isset($widget['options'])) ? $widget['options'] : array();
         $attributes = (isset($widget['attributes'])) ? $widget['attributes'] : array();
         if (isset($widget['class']))
         {
           $class = $widget['class'];
           $this->filters->setWidget($key, new $class($options, $attributes));
         }
         else
         {
           foreach ($options as $name => $value)
           {
             $this->filters->getWidget($key)->setOption($name, $value);
           }
           foreach ($attributes as $name => $value)
           {
             $this->filters->getWidget($key)->setAttribute($name, $value);
           }
         }
       }
      }
    }

    $query = $this->filters->buildCriteria($this->getFilters());
<?php else: ?>
    $query = PropelQuery::from('<?php echo $this->getModelClass() ?>');
<?php endif; ?>

    $this->processSort($query);
    
    foreach ($this->configuration->getWiths() as $with) {
      $query->joinWith($with);
    }
    
    foreach ($this->configuration->getQueryMethods() as $method) {
      $query->$method();
    }
    
    $event = $this->dispatcher->filter(new sfEvent($this, 'admin.build_criteria'), $query);
    $query = $event->getReturnValue();

    return $query;
  }
