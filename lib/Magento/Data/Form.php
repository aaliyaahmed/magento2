<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Magento
 * @package    Magento_Data
 * @copyright  Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Data;

use Magento\Data\Form\Element\AbstractElement;
use Magento\Data\Form\Element\Collection as ElementCollection;
use Magento\Data\Form\Element\CollectionFactory as ElementCollectionFactory;
use Magento\Data\Form\Element\Factory;
use Magento\Data\Form\Element\Renderer\RendererInterface;
use Magento\Data\Form\FormKey;
use Magento\Profiler;

class Form extends \Magento\Data\Form\AbstractForm
{
    /**
     * All form elements collection
     *
     * @var ElementCollection
     */
    protected $_allElements;

    /**
     * form elements index
     *
     * @var array
     */
    protected $_elementsIndex;

    /**
     * @var FormKey
     */
    protected $formKey;

    /**
     * @var RendererInterface
     */
    static protected $_defaultElementRenderer;

    /**
     * @var RendererInterface
     */
    static protected $_defaultFieldsetRenderer;

    /**
     * @var RendererInterface
     */
    static protected $_defaultFieldsetElementRenderer;

    /**
     * @param Factory $factoryElement
     * @param ElementCollectionFactory $factoryCollection
     * @param FormKey $formKey
     * @param array $data
     */
    public function __construct(
        Factory $factoryElement,
        ElementCollectionFactory $factoryCollection,
        FormKey $formKey,
        $data = array()
    ) {
        parent::__construct($factoryElement, $factoryCollection, $data);
        $this->_allElements = $this->_factoryCollection->create(array('container' => $this));
        $this->formKey = $formKey;
    }

    /**
     * @param RendererInterface $renderer
     * @return void
     */
    public static function setElementRenderer(RendererInterface $renderer = null)
    {
        self::$_defaultElementRenderer = $renderer;
    }

    /**
     * @param RendererInterface $renderer
     * @return void
     */
    public static function setFieldsetRenderer(RendererInterface $renderer = null)
    {
        self::$_defaultFieldsetRenderer = $renderer;
    }

    /**
     * @param RendererInterface $renderer
     * @return void
     */
    public static function setFieldsetElementRenderer(RendererInterface $renderer = null)
    {
        self::$_defaultFieldsetElementRenderer = $renderer;
    }

    /**
     * @return RendererInterface
     */
    public static function getElementRenderer()
    {
        return self::$_defaultElementRenderer;
    }

    /**
     * @return RendererInterface
     */
    public static function getFieldsetRenderer()
    {
        return self::$_defaultFieldsetRenderer;
    }

    /**
     * @return RendererInterface
     */
    public static function getFieldsetElementRenderer()
    {
        return self::$_defaultFieldsetElementRenderer;
    }

    /**
     * Return allowed HTML form attributes
     *
     * @return string[]
     */
    public function getHtmlAttributes()
    {
        return array('id', 'name', 'method', 'action', 'enctype', 'class', 'onsubmit', 'target');
    }

    /**
     * Add form element
     *
     * @param AbstractElement $element
     * @param bool $after
     * @return $this
     */
    public function addElement(AbstractElement $element, $after = false)
    {
        $this->checkElementId($element->getId());
        parent::addElement($element, $after);
        $this->addElementToCollection($element);
        return $this;
    }

    /**
     * Check existing element
     *
     * @param   string $elementId
     * @return  bool
     */
    protected function _elementIdExists($elementId)
    {
        return isset($this->_elementsIndex[$elementId]);
    }

    /**
     * @param AbstractElement $element
     * @return $this
     */
    public function addElementToCollection($element)
    {
        $this->_elementsIndex[$element->getId()] = $element;
        $this->_allElements->add($element);
        return $this;
    }

    /**
     * @param string $elementId
     * @return bool
     * @throws \Exception
     */
    public function checkElementId($elementId)
    {
        if ($this->_elementIdExists($elementId)) {
            throw new \InvalidArgumentException('Element with id "' . $elementId . '" already exists');
        }
        return true;
    }

    /**
     * @return $this
     */
    public function getForm()
    {
        return $this;
    }

    /**
     * Retrieve form element by id
     *
     * @param string $elementId
     * @return null|AbstractElement
     */
    public function getElement($elementId)
    {
        if ($this->_elementIdExists($elementId)) {
            return $this->_elementsIndex[$elementId];
        }
        return null;
    }

    /**
     * @param array $values
     * @return $this
     */
    public function setValues($values)
    {
        foreach ($this->_allElements as $element) {
            if (isset($values[$element->getId()])) {
                $element->setValue($values[$element->getId()]);
            } else {
                $element->setValue(null);
            }
        }
        return $this;
    }

    /**
     * @param array $values
     * @return $this
     */
    public function addValues($values)
    {
        if (!is_array($values)) {
            return $this;
        }
        foreach ($values as $elementId => $value) {
            $element = $this->getElement($elementId);
            if ($element) {
                $element->setValue($value);
            }
        }
        return $this;
    }

    /**
     * Add suffix to name of all elements
     *
     * @param string $suffix
     * @return $this
     */
    public function addFieldNameSuffix($suffix)
    {
        foreach ($this->_allElements as $element) {
            $name = $element->getName();
            if ($name) {
                $element->setName($this->addSuffixToName($name, $suffix));
            }
        }
        return $this;
    }

    /**
     * @param string $name
     * @param string $suffix
     * @return string
     */
    public function addSuffixToName($name, $suffix)
    {
        if (!$name) {
            return $suffix;
        }
        $vars = explode('[', $name);
        $newName = $suffix;
        foreach ($vars as $index => $value) {
            $newName .= '[' . $value;
            if ($index == 0) {
                $newName .= ']';
            }
        }
        return $newName;
    }

    /**
     * @param string $elementId
     * @return $this
     */
    public function removeField($elementId)
    {
        if ($this->_elementIdExists($elementId)) {
            unset($this->_elementsIndex[$elementId]);
        }
        return $this;
    }

    /**
     * @param string $prefix
     * @return $this
     */
    public function setFieldContainerIdPrefix($prefix)
    {
        $this->setData('field_container_id_prefix', $prefix);
        return $this;
    }

    /**
     * @return string
     */
    public function getFieldContainerIdPrefix()
    {
        return $this->getData('field_container_id_prefix');
    }

    /**
     * @return string
     */
    public function toHtml()
    {
        Profiler::start('form/toHtml');
        $html = '';
        $useContainer = $this->getUseContainer();
        if ($useContainer) {
            $html .= '<form ' . $this->serialize($this->getHtmlAttributes()) . '>';
            $html .= '<div>';
            if (strtolower($this->getData('method')) == 'post') {
                $html .= '<input name="form_key" type="hidden" value="'
                    . $this->formKey->getFormKey()
                    . '" />';
            }
            $html .= '</div>';
        }

        foreach ($this->getElements() as $element) {
            $html.= $element->toHtml();
        }

        if ($useContainer) {
            $html.= '</form>';
        }
        Profiler::stop('form/toHtml');
        return $html;
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->toHtml();
    }
}
