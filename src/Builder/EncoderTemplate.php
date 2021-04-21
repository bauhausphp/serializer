<?php

declare(strict_types=1);

namespace Serializer\Builder;

class EncoderTemplate
{
    private const TEMPLATE = <<<STIRNG
<?php

declare(strict_types=1);

namespace Serializer\Encoder;

use Serializer\Exception\MissingOrInvalidProperty;
use Serializer\Encoder;
use TypeError;

class [cacheClassName] extends Encoder
{
    /**
     * @param \[className] \$object
     */
    public function encode(object \$object)
    {
        return [getters];
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

    private function createGetter(ClassProperty $property): string
    {
        if ($property->isScalar()) {
            return sprintf(
                "%s'%s' => \$object->%s()",
                str_repeat(' ', 12),
                $property->getName(),
                $property->getGetter()
            );
        }

        return sprintf(
            "%s'%s' => \$this->serializer()->encode(\$object->%s())",
            str_repeat(' ', 12),
            $property->getName(),
            $property->getGetter()
        );
    }

    private function compileValueObject(string $string): string
    {
        $property = $this->definition->getProperties()[0];

        $string = str_replace('[cacheClassName]', $this->factoryName, $string);
        $string = str_replace('[className]', $this->definition->getName(), $string);
        $string = str_replace('[getters]', sprintf('(%s) $object->__toString()', $property->getType()), $string);
        $string = str_replace('[isCollection]', 'false', $string);

        return $string;
    }

    private function compileDto(string $string): string
    {
        $getters = array_map(function (ClassProperty $param) {
            return $this->createGetter($param);
        }, $this->definition->getProperties());

        $sp1 = str_repeat(" ", 12);
        $sp2 = str_repeat(" ", 8);

        $string = str_replace('[cacheClassName]', $this->factoryName, $string);
        $string = str_replace('[className]', $this->definition->getName(), $string);
        $string = str_replace('[getters]', "[\n" . $sp1 . trim(implode(",\n", $getters)) . ",\n" . $sp2 . "]", $string);
        $string = str_replace('[isCollection]', $this->definition->isCollection() ? 'true' : 'false', $string);

        return $string;
    }
}
