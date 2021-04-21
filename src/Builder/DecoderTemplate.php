<?php

declare(strict_types=1);

namespace Serializer\Builder;

class DecoderTemplate
{
    private const TEMPLATE = <<<STIRNG
<?php

declare(strict_types=1);

namespace Serializer\Decoder;

use Serializer\Exception\MissingOrInvalidProperty;
use Serializer\Decoder;
use TypeError;

class [cacheClassName] extends Decoder
{
    /**
     * @return \[className]
     */
    public function decode(\$data, ?string \$propertyName = null): object
    {
        try {
            \$object = new \[className](
                [arguments]
            );
        } catch (TypeError \$e) {
            throw new MissingOrInvalidProperty(\$e, [[properties]]);
        }

        return \$object;
    }

    public function isCollection(): bool
    {
        return [isCollection];
    }
}
STIRNG;

    private ClassDefinition $definition;
    private string $factoryName;

    public function __construct(ClassDefinition $definition, string $factoryName)
    {
        $this->definition = $definition;
        $this->factoryName = $factoryName;
    }

    public function __toString(): string
    {
        $string = self::TEMPLATE;

        if ($this->definition->isValueObject()) {
            return $this->compileValueObject($string);
        }

        return $this->compileDto($string);
    }

    private function createArgument(ClassProperty $property, ClassDefinition $definition): string
    {
        if ($property->isScalar()) {
            return sprintf(
                "%s\$data->%s ?? %s",
                str_repeat(' ', 16),
                $property->getName(),
                $property->getDefaultValue()
            );
        }

        if ($definition->isCollection()) {
            return sprintf(
                "%s...\$this->serializer()->decode(\$data ?? [], \%s::class)",
                str_repeat(' ', 16),
                $property->getType()
            );
        }

        return sprintf(
            "%s%s\$this->serializer()->decode(\$data->%s ?? %s, \%s::class, '%s')",
            str_repeat(' ', 16),
            $property->isArgument() ? '...' : '',
            $property->getName(),
            $property->getDefaultValue(),
            $property->getType(),
            $property->getName()
        );
    }

    private function compileValueObject(string $string): string
    {
        $property = $this->definition->getProperties()[0];
        $type = $property->getType();
        $name = $property->getName();
        $accessValue = sprintf('(%s) ($data->value ?? $data->%s ?? $data)', $type, $name);

        $string = str_replace('[cacheClassName]', $this->factoryName, $string);
        $string = str_replace('[className]', $this->definition->getName(), $string);
        $string = str_replace('[arguments]', $accessValue, $string);
        $string = str_replace('[properties]', '$propertyName', $string);
        $string = str_replace('[isCollection]', 'false', $string);

        return $string;
    }

    private function compileDto(string $string): string
    {
        $arguments = array_map(function (ClassProperty $param) {
            return $this->createArgument($param, $this->definition);
        }, $this->definition->getProperties());

        $properties = array_map(function (ClassProperty $param) {
            return sprintf("'%s'", $param->getName());
        }, $this->definition->getProperties());

        $string = str_replace('[cacheClassName]', $this->factoryName, $string);
        $string = str_replace('[className]', $this->definition->getName(), $string);
        $string = str_replace('[arguments]', trim(implode(",\n", $arguments)), $string);
        $string = str_replace('[properties]', trim(implode(", ", $properties)), $string);
        $string = str_replace('[isCollection]', $this->definition->isCollection() ? 'true' : 'false', $string);

        return $string;
    }
}
