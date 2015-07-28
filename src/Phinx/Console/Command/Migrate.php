<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Console
 */
namespace Phinx\Console\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->addOption('--environment', '-e', InputOption::VALUE_REQUIRED, 'The target environment');

        $this->setName('migrate')
             ->setDescription('Migrate the database')
             ->addOption('--target', '-t', InputOption::VALUE_REQUIRED, 'The version number to migrate to')
             ->setHelp(
<<<EOT
The <info>migrate</info> command runs all available migrations, optionally up to a specific version

<info>phinx migrate -e development</info>
<info>phinx migrate -e development -t 20110103081132</info>
<info>phinx migrate -e development -v</info>

EOT
             );
    }

    /**
     * Migrate the database.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        $version = $input->getOption('target');
        $environment = $input->getOption('environment');

        if ($environment == 'all') {
            $startAll = microtime(true);
            foreach (array_keys($this->config['environments']) as $environmentName) {
                if ($environmentName == 'default_migration_table' || $environmentName == 'default_database') {
                    continue;
                }
                $input->setOption('environment', $environmentName);
                $this->execute($input, $output);
            }
            $endAll = microtime(true);
            $output->writeln('<comment>All databases complete. Took ' . sprintf('%.4fs', $endAll - $startAll) . '</comment>');
            return;
        } else if (sscanf($environment, "%d/%d", $offset, $division) == 2) {
            $startAll = microtime(true);
            $environmentCount = count($this->config['environments']);
            for($i = $offset; $i < $environmentCount; $i += $division) {
                $environmentName = array_keys($this->config['environments'])[$i];
                if ($environmentName == 'default_migration_table' || $environmentName == 'default_database') {
                    continue;
                }
                $input->setOption('environment', $environmentName);
                $this->execute($input, $output);
            }
            $endAll = microtime(true);
            $output->writeln('<comment>All databases complete. Took ' . sprintf('%.4fs', $endAll - $startAll) . '</comment>');
            return;
        }

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter'])) {
            $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        }

        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name']);
        }

        if (isset($envOptions['table_prefix'])) {
            $output->writeln('<info>using table prefix</info> ' . $envOptions['table_prefix']);
        }
        if (isset($envOptions['table_suffix'])) {
            $output->writeln('<info>using table suffix</info> ' . $envOptions['table_suffix']);
        }

        // run the migrations
        $start = microtime(true);
        try {
            $this->getManager()->migrate($environment, $version);
        } catch (\PDOException $exception) {
            $message = $exception->getMessage();
            $output->writeln('<error>  --== ERROR ==--  </error> skipping :' . $message);
            $errors[$environment][] = $message;
        } catch (\InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $output->writeln('<error>  --== ERROR ==--  </error> skipping :' . $message);
            $errors[$environment][] = $message;
        }
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
