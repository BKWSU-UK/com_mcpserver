<?php

declare(strict_types=1);

namespace Joomla\Component\Mcpserver\Administrator\Service;

defined('_JEXEC') or die;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

class SchemaValidator
{
    public function validate(array $data, array $schema): ?string
    {
        $validator = new Validator();
        $dataObject = json_decode(json_encode($data));
        $schemaObject = json_decode(json_encode($schema));

        $validator->validate($dataObject, $schemaObject, Constraint::CHECK_MODE_TYPE_CAST);

        if (!$validator->isValid()) {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf('[%s] %s', $error['property'], $error['message']);
            }
            return implode('; ', $errors);
        }

        return null;
    }
}

