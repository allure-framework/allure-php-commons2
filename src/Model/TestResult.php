<?php

declare(strict_types=1);

namespace Qameta\Allure\Model;

use function array_values;

final class TestResult extends ExecutionContext
{
    protected ?string $historyId = null;

    protected ?string $testCaseId = null;

    protected ?string $rerunOf = null;

    protected ?string $fullName = null;

    protected ?string $precondition = null;

    protected ?string $preconditionHtml = null;

    protected ?string $expectedResult = null;

    protected ?string $expectedResultHtml = null;

    /**
     * @var list<string>
     */
    protected array $titlePath = [];

    /**
     * @var list<Label>
     */
    protected array $labels = [];

    /**
     * @var list<Link>
     */
    protected array $links = [];

    public function getResultType(): ResultType
    {
        return ResultType::test();
    }

    public function getHistoryId(): ?string
    {
        return $this->historyId;
    }

    public function setHistoryId(?string $historyId): self
    {
        $this->historyId = $historyId;

        return $this;
    }

    public function getTestCaseId(): ?string
    {
        return $this->testCaseId;
    }

    public function setTestCaseId(?string $testCaseId): self
    {
        $this->testCaseId = $testCaseId;

        return $this;
    }

    public function getRerunOf(): ?string
    {
        return $this->rerunOf;
    }

    public function setRerunOf(?string $rerunOf): self
    {
        $this->rerunOf = $rerunOf;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPrecondition(): ?string
    {
        return $this->precondition;
    }

    public function setPrecondition(?string $precondition): self
    {
        $this->precondition = $precondition;

        return $this;
    }

    public function getPreconditionHtml(): ?string
    {
        return $this->preconditionHtml;
    }

    public function setPreconditionHtml(?string $preconditionHtml): self
    {
        $this->preconditionHtml = $preconditionHtml;

        return $this;
    }

    public function getExpectedResult(): ?string
    {
        return $this->expectedResult;
    }

    public function setExpectedResult(?string $expectedResult): self
    {
        $this->expectedResult = $expectedResult;

        return $this;
    }

    public function getExpectedResultHtml(): ?string
    {
        return $this->expectedResultHtml;
    }

    public function setExpectedResultHtml(?string $expectedResultHtml): self
    {
        $this->expectedResultHtml = $expectedResultHtml;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTitlePath(): array
    {
        return $this->titlePath;
    }

    public function setTitlePath(string ...$items): self
    {
        $this->titlePath = array_values($items);

        return $this;
    }

    /**
     * @return list<Label>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    public function addLabels(Label ...$labels): self
    {
        return $this->setLabels(...$this->labels, ...array_values($labels));
    }

    public function setLabels(Label ...$labels): self
    {
        $this->labels = array_values($labels);

        return $this;
    }

    /**
     * @return list<Link>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    public function addLinks(Link ...$links): self
    {
        return $this->setLinks(...$this->links, ...array_values($links));
    }

    public function setLinks(Link ...$links): self
    {
        $this->links = array_values($links);

        return $this;
    }

    /**
     * @return list<string>
     */
    protected function excludeFromSerialization(): array
    {
        $excluded = parent::excludeFromSerialization();
        foreach (['precondition', 'preconditionHtml', 'expectedResult', 'expectedResultHtml'] as $field) {
            if ($this->$field === null) {
                $excluded[] = $field;
            }
        }

        return $excluded;
    }
}
