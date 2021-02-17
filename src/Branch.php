<?php namespace EugeneErg\ClassCreator;

class Branch
{
    /**
     * @var Branch|null
     */
    private $parent;

    /**
     * @var Branch[]
     */
    private $children;

    /**
     * @var mixed
     */
    private $value;

    /**
     * Branch constructor.
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @param Branch $child
     * @param string $name
     */
    public function addChild(Branch $child, string $name): void
    {
        $child->parent = $this;
        $this->children[$name] = $child;
    }

    /**
     * @param string $name
     * @return Branch
     */
    public function getChild(string $name): Branch
    {
        return $this->children[$name];
    }

    /**
     * @return Branch[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasChild(string $name): bool
    {
        return isset($this->children[$name]);
    }

    /**
     * @param string[] $names
     * @return Branch
     */
    public function getDescendant(array $names): Branch
    {
        $child = $this->getChild(array_shift($names));

        return count($names) ? $child->getDescendant($names) : $child;
    }

    /**
     * @param string[] $names
     * @return bool
     */
    public function hasDescendant(array $names): bool
    {
        $childName = array_shift($names);

        if (!$this->hasChild($childName)) {
            return false;
        }

        return count($names) ? $this->getChild($childName)->hasDescendant($names) : true;
    }

    /**
     * @param string[] $names
     * @return string[]
     */
    public function getPossibleDescendantPath(array $names): array
    {
        $childName = array_shift($names);

        if (!$this->hasChild($childName)) {
            return [];
        }

        $result = $this->getChild($childName)->getPossibleDescendantPath($names);
        array_unshift($result, $childName);

        return $result;
    }

    /**
     * @param int $level
     * @return int
     */
    public function getPossibleAncestorLevel(int $level = null): int
    {
        if (!$this->hasParent()) {
            return 0;
        }

        return $this->getParent()->getPossibleAncestorLevel($level === null ? null : $level - 1) + 1;
    }

    /**
     * @param int|null $level
     * @return string[]
     */
    public function getPossibleAncestorPath(int $level = null): array
    {
        if (!$this->hasParent()) {
            return [];
        }

        $result = $this->getParent()->getPossibleAncestorPath($level === null ? null : $level - 1);
        array_unshift($result, $this->getParent()->getChildrenName($this));

        return $result;
    }

    /**
     * @param Branch $needed
     * @return string|null
     */
    private function getChildrenName(Branch $needed): ?string
    {
        foreach ($this->children as $name => $child) {
            if ($needed === $child) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return Branch|null
     */
    public function getParent(): ?Branch
    {
        return $this->parent;
    }

    /**
     * @return bool
     */
    public function hasParent(): bool
    {
        return $this->parent !== null;
    }

    /**
     * @param int $level
     * @return Branch|null
     */
    public function getAncestor(int $level = 1): ?Branch
    {
        $parent = $this->getParent();

        return $level > 1 ? $parent->getAncestor($level - 1) : $parent;
    }

    /**
     * @param int $level
     * @return bool
     */
    public function hasAncestor(int $level = 1): bool
    {
        if (!$this->hasParent()) {
            return false;
        }

        return $level > 1 ? $this->getParent()->hasAncestor($level - 1) : true;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}