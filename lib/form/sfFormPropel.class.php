<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfFormPropel is the base class for forms based on Propel objects.
 *
 * This class extends BaseForm, a class generated automatically with each new project.
 *
 * @package    symfony
 * @subpackage form
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: sfFormPropel.class.php 24068 2009-11-17 06:39:35Z Kris.Wallsmith $
 */
abstract class sfFormPropel extends sfFormObject
{
  protected $ignoreIfEmpty = false;
  protected $fixedValues = array();
  
  /**
   * Constructor.
   *
   * @param mixed  A object used to initialize default values
   * @param array  An array of options
   * @param string A CSRF secret (false to disable CSRF protection, null to use the global CSRF secret)
   *
   * @see sfForm
   */
  public function __construct($object = null, $options = array(), $CSRFSecret = null)
  {
    $class = $this->getModelName();
    if (!$object)
    {
      $this->object = new $class();
    }
    else
    {
      if (!$object instanceof $class)
      {
        throw new sfException(sprintf('The "%s" form only accepts a "%s" object.', get_class($this), $class));
      }

      $this->object = $object;
      $this->isNew = $this->getObject()->isNew();
    }

    parent::__construct(array(), $options, $CSRFSecret);

    $this->updateDefaultsFromObject();
  }

  /**
   * @return PropelPDO
   * @see sfFormObject
   */
  public function getConnection()
  {
    return Propel::getConnection(constant($this->getPeer().'::DATABASE_NAME'));
  }

  /**
   * Embeds i18n objects into the current form.
   *
   * @param array   $cultures   An array of cultures
   * @param string  $decorator  A HTML decorator for the embedded form
   */
  public function embedI18n($cultures, $decorator = null)
  {
    if (!$this->isI18n())
    {
      throw new sfException(sprintf('The model "%s" is not internationalized.', $this->getModelName()));
    }

    $class = $this->getI18nFormClass();
    foreach ($cultures as $culture)
    {
      $method = sprintf('getCurrent%s', $this->getI18nModelName($culture));
      $i18nObject = $this->getObject()->$method($culture);
      $i18n = new $class($i18nObject);
      
      if ($i18nObject->isNew())
      {
        unset($i18n['id'], $i18n['culture']);
      }

      $this->embedForm($culture, $i18n, $decorator);
    }
  }

  /**
   * @see sfFormObject
   */
  protected function doUpdateObject($values)
  {
    print_r($values);
    $values = array_merge($values, $this->getFixedValues());
    $this->getObject()->fromArray($values, BasePeer::TYPE_FIELDNAME);
  }

  /**
   * Processes cleaned up values with user defined methods.
   *
   * To process a value before it is used by the updateObject() method,
   * you need to define an updateXXXColumn() method where XXX is the PHP name
   * of the column.
   *
   * The method must return the processed value or false to remove the value
   * from the array of cleaned up values.
   *
   * @see sfFormObject
   */
  public function processValues($values)
  {
    // see if the user has overridden some column setter
    $valuesToProcess = $values;
    foreach ($valuesToProcess as $field => $value)
    {
      try
      {
        $method = sprintf('update%sColumn', call_user_func(array($this->getPeer(), 'translateFieldName'), $field, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_PHPNAME));
      }
      catch (Exception $e)
      {
        // not a "real" column of this object
        if (!method_exists($this, $method = sprintf('update%sColumn', self::camelize($field))))
        {
          continue;
        }
      }

      if (method_exists($this, $method))
      {
        if (false === $ret = $this->$method($value))
        {
          unset($values[$field]);
        }
        else
        {
          $values[$field] = $ret;
        }
      }
      else
      {
        // save files
        if ($this->validatorSchema[$field] instanceof sfValidatorFile)
        {
          $values[$field] = $this->processUploadedFile($field, null, $valuesToProcess);
        }
      }
    }

    return $values;
  }

  /**
   * Returns true if the current form has some associated i18n objects.
   *
   * @return Boolean true if the current form has some associated i18n objects, false otherwise
   */
  public function isI18n()
  {
    return null !== $this->getI18nFormClass();
  }

  /**
   * Returns the name of the i18n model.
   *
   * @return string The name of the i18n model
   */
  public function getI18nModelName()
  {
    return null;
  }

  /**
   * Returns the name of the i18n form class.
   *
   * @return string The name of the i18n form class
   */
  public function getI18nFormClass()
  {
    return null;
  }

  /**
   * Updates the default values of the form with the current values of the current object.
   */
  protected function updateDefaultsFromObject()
  {
    // update defaults for the main object
    if ($this->isNew())
    {
      $this->setDefaults(array_merge($this->getObject()->toArray(BasePeer::TYPE_FIELDNAME), $this->getDefaults()));
    }
    else
    {
      $this->setDefaults(array_merge($this->getDefaults(), $this->getObject()->toArray(BasePeer::TYPE_FIELDNAME)));
    }
  }

  /**
   * Saves the uploaded file for the given field.
   *
   * @param  string $field The field name
   * @param  string $filename The file name of the file to save
   * @param  array  $values An array of values
   *
   * @return string The filename used to save the file
   */
  protected function processUploadedFile($field, $filename = null, $values = null)
  {
    if (!$this->validatorSchema[$field] instanceof sfValidatorFile)
    {
      throw new LogicException(sprintf('You cannot save the current file for field "%s" as the field is not a file.', $field));
    }

    if (null === $values)
    {
      $values = $this->values;
    }

    if (isset($values[$field.'_delete']) && $values[$field.'_delete'])
    {
      $this->removeFile($field);

      return '';
    }

    if (!$values[$field])
    {
      $column = call_user_func(array($this->getPeer(), 'translateFieldName'), $field, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_PHPNAME);
      $getter = 'get'.$column;

      return $this->getObject()->$getter();
    }

    // we need the base directory
    if (!$this->validatorSchema[$field]->getOption('path'))
    {
      return $values[$field];
    }

    $this->removeFile($field);

    return $this->saveFile($field, $filename, $values[$field]);
  }

  /**
   * Removes the current file for the field.
   *
   * @param string $field The field name
   */
  protected function removeFile($field)
  {
    if (!$this->validatorSchema[$field] instanceof sfValidatorFile)
    {
      throw new LogicException(sprintf('You cannot remove the current file for field "%s" as the field is not a file.', $field));
    }

    $column = call_user_func(array($this->getPeer(), 'translateFieldName'), $field, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_PHPNAME);
    $getter = 'get'.$column;

    if (($directory = $this->validatorSchema[$field]->getOption('path')) && is_file($directory.DIRECTORY_SEPARATOR.$this->getObject()->$getter()))
    {
      unlink($directory.DIRECTORY_SEPARATOR.$this->getObject()->$getter());
    }
  }
  
  public function getPeer()
  {
    return constant(get_class($this->getObject()).'::PEER');
  }
  
  /**
   * Saves the current file for the field.
   *
   * @param  string          $field    The field name
   * @param  string          $filename The file name of the file to save
   * @param  sfValidatedFile $file     The validated file to save
   *
   * @return string The filename used to save the file
   */
  protected function saveFile($field, $filename = null, sfValidatedFile $file = null)
  {
    if (!$this->validatorSchema[$field] instanceof sfValidatorFile)
    {
      throw new LogicException(sprintf('You cannot save the current file for field "%s" as the field is not a file.', $field));
    }

    if (null === $file)
    {
      $file = $this->getValue($field);
    }

    $column = call_user_func(array($this->getPeer(), 'translateFieldName'), $field, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_PHPNAME);
    $method = sprintf('generate%sFilename', $column);

    if (null !== $filename)
    {
      return $file->save($filename);
    }
    else if (method_exists($this, $method))
    {
      return $file->save($this->$method($file));
    }
    else if (method_exists($this->getObject(), $method))
    {
      return $file->save($this->getObject()->$method($file));
    }
    else
    {
      return $file->save();
    }
  }
  
  /**
   * Overrides sfForm::mergeForm() to also merge embedded forms
   * Allows autosave of marged collections
   *
   * @param  sfForm   $form      The sfForm instance to merge with current form
   *
   * @throws LogicException      If one of the form has already been bound
   */
  public function mergeForm(sfForm $form)
  {
    foreach ($form->getEmbeddedForms() as $name => $embeddedForm)
    {
      $this->embedForm($name, clone $embeddedForm);
    }
    parent::mergeForm($form);
  }
  
  /**
   * Merge Relation form into this form
   */
  public function mergeRelation($relationName, $options = array())
  {
    $relationForm = $this->getRelationForm($relationName, $options);
    
    $this->mergeForm($relationForm);
  }
  
  public function embedRelation($relationName, $options = array())
  {
    $options = array_merge(array(
      'title'               => $relationName,
      'decorator'           => null
    ), $options);
    
    $relationForm = $this->getRelationForm($relationName, $options);
    
    $this->embedForm($options['title'], $relationForm, $options['decorator']);
  }
  
  public function getRelationForm($relationName, $options = array())
  {
    $options = array_merge(array(
      'embedded_form_class' => null,
      'item_pattern'        => '%index%',
      'add_empty'           => false,
      'empty_name'          => null,
      'hide_on_new'         => false,
    ), $options);
    
    if ($this->getObject()->isNew() && $options['hide_on_new'])
    {
      return;
    }
    
    // compute relation elements
    $tableMap = call_user_func(array($this->getPeer(), 'getTableMap'));
    $relationMap = $tableMap->getRelation($relationName);
    if ($relationMap->getType() != RelationMap::ONE_TO_MANY)
    {
      throw new sfException('embedRelation() only works for one-to-many relationships');
    }
    $collection = call_user_func(array($this->getObject(), sprintf('get%ss', $relationName)));
    $relatedPeer = $relationMap->getRightTable()->getPeerClassname();
    
    // compute relation fields, to be removed from embedded forms
    // because this data is not editable
    $relationFields = array();
    foreach ($relationMap->getColumnMappings(RelationMap::LEFT_TO_RIGHT) as $leftCol => $rightCol)
    {
      $relationFields[$leftCol]= call_user_func(array($relatedPeer, 'translateFieldName'), $rightCol, BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME);
    }
    
    // create the relation form
    $collectionForm = new sfFormPropelCollection($collection, array(
      'embedded_form_class' => $options['embedded_form_class'],
      'item_pattern'        => $options['item_pattern'],
      'remove_fields'       => $relationFields,
    ));
    
    // add empty form for addition
    // FIXME new relations saved at each edit
    if ($options['add_empty'])
    {
      if (null === $options['empty_name']) {
        $options['empty_name'] = 'new' . $relationMap->getName();
      }
      $relatedClass = $relationMap->getRightTable()->getClassname();
      $formClass = $collectionForm->getFormClass();
      $emptyForm = new $formClass(new $relatedClass());
      $relationValues = array();
      foreach ($relationFields as $leftCol => $field)
      {
        unset($emptyForm[$field]);
        $emptyForm->setFixedValue($field, $this->getObject()->getByName($leftCol, BasePeer::TYPE_COLNAME));
      }
      $collectionForm->embedForm($options['empty_name'], $emptyForm);
    }
    
    return $collectionForm;
  }
  
  public function setFixedValues($values)
  {
    $this->fixedValues = $values;
  }

  public function setFixedValue($name, $value)
  {
    $this->fixedValues[$name] = $value;
  }

  public function getFixedValues()
  {
    return $this->fixedValues;
  }

}
