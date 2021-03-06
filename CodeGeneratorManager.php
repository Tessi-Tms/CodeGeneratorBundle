<?php

/**
 * @author:  Baptiste BOUCHEREAU <baptiste.bouchereau@idci-consulting.fr>
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @license: MIT
 */

namespace IDCI\Bundle\CodeGeneratorBundle;

use Symfony\Component\OptionsResolver\OptionsResolver;
use IDCI\Bundle\CodeGeneratorBundle\Configuration\CodeGeneratorConfiguratorBuilderInterface;
use IDCI\Bundle\CodeGeneratorBundle\Generation\CodeGeneratorRegistryInterface;
use IDCI\Bundle\CodeGeneratorBundle\Validation\CodeValidatorRegistryInterface;
use IDCI\Bundle\CodeGeneratorBundle\Model\GenerationConfiguration;
use IDCI\Bundle\CodeGeneratorBundle\Exception\InvalidConfigurationException;

class CodeGeneratorManager
{
    /**
     * @var CodeGeneratorConfiguratorBuilderInterface
     */
    private $configuratorBuilder;

    /**
     * @var CodeGeneratorRegistryInterface
     */
    private $generatorRegistry;

    /**
     * @var CodeValidatorRegistryInterface
     */
    private $validatorRegistry;

    /**
     * Constructor.
     *
     * @param CodeGeneratorConfiguratorBuilderInterface $configuratorBuilder
     * @param CodeGeneratorRegistryInterface            $generatorRegistry
     * @param CodeValidatorRegistryInterface            $validatorRegistry
     */
    public function __construct(
        CodeGeneratorConfiguratorBuilderInterface $configuratorBuilder,
        CodeGeneratorRegistryInterface   $generatorRegistry,
        CodeValidatorRegistryInterface   $validatorRegistry
    )
    {
        $this->configuratorBuilder = $configuratorBuilder;
        $this->generatorRegistry   = $generatorRegistry;
        $this->validatorRegistry   = $validatorRegistry;
    }

    /**
     * Generate codes.
     *
     * @param integer                 $quantity         The quantity of codes to generate.
     * @param GenerationConfiguration $configuration    The generation configuration.
     * @param string                  $generatorAlias   The generator alias to use.
     * @param array                   $validators       The validators to use.
     *
     * @return array $codes The generated codes.
     *
     * @throws InvalidConfigurationException
     */
    public function generate(
        $quantity = 42,
        GenerationConfiguration $configuration = null,
        $generatorAlias = 'random',
        array $validators = array())
    {
        $configuration = null === $configuration ?
            new GenerationConfiguration() :
            $configuration
        ;

        // Build the configurator
        $configurator = $this
            ->configuratorBuilder
            ->build($configuration)
        ;

        // Ensure we can generate as much codes as asked
        if ($quantity > $configurator->getMaxQuantity()) {
            throw new InvalidConfigurationException(sprintf(
                'The asked codes generation quantity `%d` is upper than max quantity of codes that could be generated `%d`',
                $quantity,
                $configurator->getMaxQuantity()
            ));
        }

        $codes = array();
        while (count($codes) < $quantity) {
            // Generate the codes
            $code = $this
                ->generatorRegistry
                ->getCodeGenerator($generatorAlias)
                ->generate($configurator)
            ;

            // Do not allow same generated codes
            if (
                isset($codes[$code]) ||
                !$this->isCodeValid($code, $validators)
            ) {
                continue;
            }

            $codes[$code] = $code;
        }

        return $codes;
    }

    /**
     * Returns whether the given code is valid or not
     *
     * @param string $code       The code to validate.
     * @param array  $validators The validators to use.
     *
     * @return boolean
     */
    protected function isCodeValid($code, array $validators = array())
    {
        foreach ($validators as $alias => $options) {
            $validator = $this->validatorRegistry->getCodeValidator($alias);
            $resolver = new OptionsResolver();
            $validator->setDefaultOptions($resolver);
            $resolvedOptions = $resolver->resolve($options);

            if (!$validator->validate($code, $resolvedOptions)) {
                return false;
            }
        }

        return true;
    }
}
