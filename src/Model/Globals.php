<?php

namespace Qameta\Allure\Model;

final class Globals extends Result
{
    /**
     * @var list<GlobalAttachment>
     */
    protected array $attachments = [];

    /**
     * @var list<GlobalError>
     */
    protected array $errors = [];

    /**
     * @return list<GlobalAttachment>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function addAttachment(GlobalAttachment $attachment): self
    {
        array_push($this->attachments, $attachment);

        return $this;
    }

    /**
     * @return list<GlobalError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addError(GlobalError $error): self
    {
        array_push($this->errors, $error);

        return $this;
    }

    public function getResultType(): ResultType
    {
        return ResultType::globals();
    }

    /**
     * @return list<ResultInterface>
     */
    final public function getNestedResults(): array
    {
        return [
            ...$this->attachments,
        ];
    }
}
