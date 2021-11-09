<?php

declare(strict_types=1);


namespace Pixelant\Interest\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract command for handling operations with entrypoint and remoteId.
 */
abstract class AbstractRecordCommandController extends Command
{
    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this
            ->addOption(
                'data',
                'd',
                InputOption::VALUE_REQUIRED,
                'JSON-encoded data. Can also be piped through stdin.'
            )
            ->addOption(
                'metaData',
                'm',
                InputOption::VALUE_REQUIRED,
                'JSON-encoded metadata. Not persisted, but used in processing.'
            )
            ->addOption(
                'batch',
                'b',
                InputOption::VALUE_NONE,
                'If set, <remoteId> is ignored and <data> is an array where each key is a remote ID and each '
                . 'value field data. Each set of remote ID and field data will be processed.'
            );
    }

    /**
     * @inheritDoc
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('data') === null && $input instanceof StreamableInputInterface) {
            $stream = $input->getStream() ?? STDIN;

            if (is_resource($stream)) {
                stream_set_blocking($stream, false);

                $rawData = '';

                // We're not using rewind() on the stream because it has a high performance cost.
                while (!feof($stream)) {
                    $rawData .= fread($stream, 1024);
                }

                $input->setOption('data', $rawData);
            }
        }

        if ($input->getOption('data') !== null) {
            $data = json_decode($input->getOption('data'), true);

            if (!is_array($data)) {
                throw new InvalidOptionException(
                    'Could not parse JSON data. Please ensure the option "data" is valid JSON. '
                    . 'Received: "' . $input->getOption('data') . '"',
                    1634238071534
                );
            }

            if ($input->getOption('batch')) {
                $input->setOption('data', $data);
            } else {
                $input->setOption('data', [$input->getArgument('remoteId') => $data]);
            }
        }

        if ($input->getOption('metaData') !== null) {
            $data = json_decode($input->getOption('metaData'), true);

            if (!is_array($data)) {
                throw new InvalidOptionException(
                    'Could not parse JSON data. Please ensure the option "metaData" is valid JSON. '
                    . 'Received: "' . $input->getOption('metaData') . '"',
                    1634238294734
                );
            }

            $input->setOption('metaData', $data);
        }
    }
}
