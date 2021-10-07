<?php

declare(strict_types=1);


namespace Pixelant\Interest\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract command for handling operations with entrypoint and remoteId.
 */
class AbstractRecordCommandController extends \Symfony\Component\Console\Command\Command
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->addOption(
            'data',
            'd',
            InputArgument::REQUIRED,
            'JSON-encoded data. Can also be piped through stdin.'
        );
    }

    /**
     * @inheritDoc
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('data') === false && $input instanceof StreamableInputInterface) {
            $stream = $input->getStream() ?? STDIN;

            if (is_resource($stream)) {
                stream_set_blocking($stream, false);

                // We're not using rewind() on the stream because it has a high performance cost.

                $input->setOption('data', stream_get_contents($stream));
            }
        }
    }
}
