<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Util;

use phpDocumentor\Reflection\Type as DocType;
use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Null_;
use Symfony\Component\PropertyInfo\Type;

/**
 * Transforms a php doc type to a {@link Type} instance.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Guilhem N. <egetick@gmail.com>
 */
final class PhpDocTypeHelper
{
    /**
     * Creates a {@see Type} from a PHPDoc type.
     *
     * @return Type[]
     */
    public function getTypes(DocType $varType): array
    {
        $types = array();
        $nullable = false;

        if (!$varType instanceof Compound) {
            if ($varType instanceof Null_) {
                $nullable = true;
            }

            $type = $this->createType($varType, $nullable);
            if (null !== $type) {
                $types[] = $type;
            }

            return $types;
        }

        $varTypes = array();
        for ($typeIndex = 0; $varType->has($typeIndex); ++$typeIndex) {
            $type = $varType->get($typeIndex);

            // If null is present, all types are nullable
            if ($type instanceof Null_) {
                $nullable = true;
                continue;
            }

            $varTypes[] = $type;
        }

        foreach ($varTypes as $varType) {
            $type = $this->createType($varType, $nullable);
            if (null !== $type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Creates a {@see Type} from a PHPDoc type.
     */
    private function createType(DocType $type, bool $nullable): ?Type
    {
        $docType = (string) $type;

        if ($type instanceof Collection) {
            list($phpType, $class) = $this->getPhpTypeAndClass((string) $type->getFqsen());

            $key = $this->getTypes($type->getKeyType());
            $value = $this->getTypes($type->getValueType());

            // More than 1 type returned means it is a Compound type, which is
            // not handled by Type, so better use a null value.
            $key = 1 === \count($key) ? $key[0] : null;
            $value = 1 === \count($value) ? $value[0] : null;

            return new Type($phpType, $nullable, $class, true, $key, $value);
        }

        // Cannot guess
        if (!$docType || 'mixed' === $docType) {
            return null;
        }

        if ($collection = '[]' === substr($docType, -2)) {
            $docType = substr($docType, 0, -2);
        }

        $docType = $this->normalizeType($docType);
        list($phpType, $class) = $this->getPhpTypeAndClass($docType);

        $array = 'array' === $docType;

        if ($collection || $array) {
            if ($array || 'mixed' === $docType) {
                $collectionKeyType = null;
                $collectionValueType = null;
            } else {
                $collectionKeyType = new Type(Type::BUILTIN_TYPE_INT);
                $collectionValueType = new Type($phpType, $nullable, $class);
            }

            return new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, $collectionKeyType, $collectionValueType);
        }

        return new Type($phpType, $nullable, $class);
    }

    private function normalizeType(string $docType): string
    {
        switch ($docType) {
            case 'integer':
                return 'int';

            case 'boolean':
                return 'bool';

            // real is not part of the PHPDoc standard, so we ignore it
            case 'double':
                return 'float';

            case 'callback':
                return 'callable';

            case 'void':
                return 'null';

            default:
                return $docType;
        }
    }

    private function getPhpTypeAndClass(string $docType): array
    {
        if (in_array($docType, Type::$builtinTypes)) {
            return array($docType, null);
        }

        return array('object', substr($docType, 1));
    }
}