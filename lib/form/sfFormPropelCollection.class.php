<?php


class sfFormPropelCollection extends sfForm
{
  protected $model;
  protected $collection;
  protected $isEmpty = false;
  
  public function __construct($collection = null, $options = array(), $CSRFSecret = null)
  {
    $options = array_merge(array(
      'item_pattern' => '%index%',
      'remove_fields' => array(),
    ), $options);
    if (!$collection)
    {
      $this->model = $options['model'];
      $collection = new PropelObjectcollection();
      $collection->setModel($this->model);
      $this->collection = $collection;
    }
    else
    {
      if (!$collection instanceof PropelObjectCollection)
      {
        throw new sfException(sprintf('The "%s" form only accepts a PropelObjectCollection object.', get_class($this)));
      }
      $this->collection = $collection;
      $this->model = $collection->getModel();
    }
    
    $this->isEmpty = $this->getCollection()->isEmpty();

    parent::__construct(array(), $options, $CSRFSecret);
  }
  
  public function configure()
  {
    $formClass = $this->getFormClass();
    $i = 1;
    foreach ($this->getCollection() as $relatedObject)
    {
      $form = new $formClass($relatedObject);
      foreach ($this->getOption('remove_fields') as $field)
      {
        unset($form[$field]);
      }
      $name = strtr($this->getOption('item_pattern'), array('%index%' => $i, '%model%' => $this->getModel()));
      $this->embedForm($name, $form);
      $i++;
    }
  }
  
  public function getCollection()
  {
    return $this->collection;
  }
  
  public function getModel()
  {
    return $this->model;
  }
  
  public function isEmpty()
  {
    return $this->isEmpty;
  }
  
  public function getFormClass()
  {
    if (!$class = $this->getOption('embedded_form_class', false))
    {
      $class = $this->getCollection()->getModel() . 'Form';
    }
    
    return $class;
  }
}