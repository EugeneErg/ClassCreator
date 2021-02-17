<?php namespace EugeneErg\ClassCreator;

class ValueIndexService
{
    /**
     * @var array
     */
    private array $values = [];

    /**
     * @param mixed ...$values
     * @return string
     */
    public function getIndex(...$values): string
    {
        $result = [];

        foreach ($values as $value) {
            $index = array_search($value, $this->values, true);

            if ($index === false) {
                $index = count($this->values);
                $this->values[] = $value;
            }

            $result[] = $index;
        }

        return implode('_', $result);
    }
}