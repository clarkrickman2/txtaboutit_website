<?php
/**
 * This file is part of the LdapTools package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LdapTools\Hydrator;

use LdapTools\AttributeConverter\AttributeConverterInterface;
use LdapTools\Exception\InvalidArgumentException;
use LdapTools\Exception\LogicException;
use LdapTools\Operation\AddOperation;
use LdapTools\Operation\BatchModifyOperation;
use LdapTools\Operation\LdapOperationInterface;
use LdapTools\Operation\QueryOperation;
use LdapTools\Query\OperatorCollection;
use LdapTools\Resolver\BaseValueResolver;
use LdapTools\Resolver\ParameterResolver;
use LdapTools\Utilities\LdapUtilities;

/**
 * Converts LDAP operation data based on a schema and its properties.
 */
class OperationHydrator extends ArrayHydrator
{
    use HydrateQueryTrait;

    /**
     * @var LdapOperationInterface
     */
    protected $operation;

    /**
     * @var string $alias The current alias for the context of a query operation.
     */
    protected $alias;

    /**
     * {@inheritdoc}
     */
    public function hydrateToLdap($operation, $dn = null)
    {
        $this->operation = $operation;
        
        if (!($operation instanceof LdapOperationInterface)) {
            throw new InvalidArgumentException('Expects an instance of LdapOperationInterface to convert to LDAP.');
        }
        if (!$this->schema && !($operation instanceof QueryOperation)) {
            return $operation;
        }

        return $this->hydrateOperation($operation);
    }

    /**
     * Set the current alias that the operation is targeting (in the context of a query operation).
     * 
     * @param null|string $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
    }

    /**
     * @param LdapOperationInterface $operation
     * @return LdapOperationInterface
     */
    protected function hydrateOperation(LdapOperationInterface $operation)
    {
        if ($operation instanceof BatchModifyOperation) {
            $this->setOperationType(AttributeConverterInterface::TYPE_MODIFY);
            $this->hydrateModifyOperation($operation);
        } elseif ($operation instanceof AddOperation) {
            $this->setOperationType(AttributeConverterInterface::TYPE_CREATE);
            $this->hydrateAddOperation($operation);
        } elseif ($operation instanceof QueryOperation) {
            $this->setOperationType(AttributeConverterInterface::TYPE_SEARCH_TO);
            $this->hydrateQueryOperation($operation);
        }

        return $operation;
    }

    /**
     * @param BatchModifyOperation $operation
     * @return BatchModifyOperation
     */
    protected function hydrateModifyOperation(BatchModifyOperation $operation)
    {
        $batches = $this->convertValuesToLdap($operation->getBatchCollection(), $operation->getDn());

        foreach ($batches as $batch) {
            /** @var \LdapTools\BatchModify\Batch $batch */
            $batch->setAttribute(
                $this->schema->getAttributeToLdap($batch->getAttribute())
            );
        }

        return $operation;
    }

    /**
     * @param AddOperation $operation
     */
    protected function hydrateAddOperation(AddOperation $operation)
    {
        $this->setDefaultParameters();
        $operation->setAttributes(parent::hydrateToLdap($operation->getAttributes()));
        $this->setDnToUse($operation);
        $operation->setAttributes($this->filterAttributeValues($operation->getAttributes()));
    }

    /**
     * @param QueryOperation $operation
     */
    protected function hydrateQueryOperation(QueryOperation $operation)
    {
        $operation->setAttributes($this->getAttributesToLdap($operation->getAttributes(), true, $this->schema, $this->alias));
        // Only want it set if it wasn't explicitly set...
        if ($this->schema && is_null($operation->getBaseDn())) {
            $operation->setBaseDn($this->schema->getBaseDn());
        }

        // Empty check instead of null due to the way the BaseDN is set for a RootDSE query...
        if (!empty($operation->getBaseDn()) && ParameterResolver::hasParameters($operation->getBaseDn())) {
            $this->setDefaultParameters();
            $operation->setBaseDn($this->resolveParameters(['baseDn' => $operation->getBaseDn()])['baseDn']);
        }
        // If null then we default to the domain config or the explicitly set value...
        if ($this->schema && !is_null($this->schema->getUsePaging())) {
            $operation->setUsePaging($this->schema->getUsePaging());
        }
        if ($this->schema && !is_null($this->schema->getScope())) {
            $operation->setScope($this->schema->getScope());
        }
        if ($this->schema) {
            $operation->addControl(...$this->schema->getControls());
        }
        if ($operation->getFilter() instanceof OperatorCollection) {
            $this->convertValuesToLdap($operation->getFilter());
            $operation->setFilter($operation->getFilter()->toLdapFilter($this->alias));
        }
    }

    /**
     * Builds the DN based off of the "name" attribute. The name attribute should be mapped to the "cn" attribute in
     * pretty much all cases except for creating an OU object. Then the "name" attribute should be mapped to "ou".
     *
     * @param AddOperation $operation
     */
    protected function setDnToUse(AddOperation $operation)
    {
        // If the DN was explicitly set, don't do anything.
        if ($operation->getDn()) {
            return;
        }

        if (!$this->schema) {
            throw new LogicException("You must explicitly set the DN or specify a schema type.");
        }
        if (!$this->schema->hasAttribute('name')) {
            throw new LogicException(
                'To create an object you must specify the name attribute in the schema. That attribute should typically'
                .' map to the "cn" attribute, as it will use that as the base of the distinguished name.'
            );
        }

        $location = $operation->getLocation() ?: $this->schema->getDefaultContainer();
        if (empty($location)) {
            throw new LogicException('You must specify a container or OU to place this LDAP object in.');
        }
        $attribute = $this->schema->getAttributeToLdap('name');
        $rdnValue = LdapUtilities::escapeValue($operation->getAttributes()[$attribute], null, LDAP_ESCAPE_DN);
        $location = $this->resolveParameters(['container' => $location])['container'];

        $operation->setDn($attribute.'='.$rdnValue.','.$location);
    }

    /**
     * Set some default parameters based off the connection.
     *
     * @return array
     */
    protected function setDefaultParameters()
    {
        if (!$this->connection) {
            return;
        }
        $this->parameters['_domainname_'] = $this->connection->getConfig()->getDomainName();
        $rootDse = $this->connection->getRootDse();

        // Would this ever not be true? I'm unable to find any RFCs specifically regarding Root DSE structure.
        if ($rootDse->has('defaultNamingContext')) {
            $this->parameters['_defaultnamingcontext_'] = $rootDse->get('defaultNamingContext');
        }
        if ($rootDse->has('configurationNamingContext')) {
            $this->parameters['_configurationnamingcontext_'] = $rootDse->get('configurationNamingContext');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configureValueResolver(BaseValueResolver $valueResolver, $dn = null)
    {
        parent::configureValueResolver($valueResolver, $dn);
        $valueResolver->setOperation($this->operation);
    }

    /**
     * Remove empty strings and null values from attribute value arrays. This prevents errors when saving to LDAP and
     * these are present for some reason.
     *
     * @param array $attributes
     * @return array
     */
    protected function filterAttributeValues(array $attributes)
    {
        return array_filter($attributes, function ($value) {
            if (is_array($value) && empty($value)) {
                return false;
            }

            return !($value === '' || $value === null);
        });
    }
}
