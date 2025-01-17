<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Mapper\Source;

use CuyZ\Valinor\Mapper\Source\Exception\FileExtensionNotHandled;
use CuyZ\Valinor\Mapper\Source\Exception\UnableToReadFile;
use Iterator;
use IteratorAggregate;
use SplFileObject;
use Traversable;

use function strtolower;

/**
 * @implements IteratorAggregate<mixed>
 */
final class FileSource implements IteratorAggregate, IdentifiableSource
{
    private string $filePath;

    /** @var Traversable<mixed> */
    private Traversable $delegate;

    public function __construct(SplFileObject $file)
    {
        $this->filePath = $file->getPathname();

        $content = $file->fread($file->getSize());

        /** @infection-ignore-all */
        // @codeCoverageIgnoreStart
        if ($content === false) {
            throw new UnableToReadFile($this->filePath);
        }
        // @codeCoverageIgnoreEnd

        switch (strtolower($file->getExtension())) {
            case 'json':
                $this->delegate = new JsonSource($content);
                break;
            case 'yaml':
            case 'yml':
                $this->delegate = new YamlSource($content);
                break;
            default:
                throw new FileExtensionNotHandled($file->getExtension());
        }
    }

    public function sourceName(): string
    {
        return $this->filePath;
    }

    /**
     * @return Iterator<mixed>
     */
    public function getIterator(): Iterator
    {
        yield from $this->delegate;
    }
}
