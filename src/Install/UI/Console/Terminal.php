<?php

declare(strict_types=1);

namespace SightPress\Core\Install\UI\Console;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Terminal output that works with a screen reader (PLAN §3.4): one plain
 * question per line with the default in words, numbered choices, progress as
 * text, "Passed" and "Failed" in words, no colour-only meaning, no spinners
 * or screen clearing, and errors that name the problem and ask again.
 */
final readonly class Terminal
{
    public function __construct(
        private InputInterface $input,
        private OutputInterface $output,
        private QuestionHelper $helper = new QuestionHelper(),
    ) {
    }

    public function line(string $text = ''): void
    {
        $this->output->writeln($text, OutputInterface::OUTPUT_PLAIN);
    }

    public function raw(string $text): void
    {
        $this->output->write($text, false, OutputInterface::OUTPUT_RAW);
    }

    public function step(int $number, int $total, string $title): void
    {
        $this->line('');
        $this->line(\sprintf('Step %d of %d: %s', $number, $total, $title));
    }

    public function passed(string $what): void
    {
        $this->line('Passed: '.$what);
    }

    public function failed(string $what): void
    {
        $this->line('Failed: '.$what);
    }

    /**
     * Asks until the answer is valid. An empty answer takes the default.
     *
     * @param callable(string): ?string $validate returns an error that names the field, or null
     */
    public function ask(string $label, ?string $default, callable $validate, bool $hidden = false, bool $hasCurrent = false): string
    {
        if ($hidden) {
            $this->line('Typing won\'t be shown, and nothing is printed as you type.');
        }
        $suffix = match (true) {
            $hidden && $hasCurrent => ' (default: keep the current value)',
            $hidden => '',
            null !== $default && '' !== $default => ' (default: '.$default.')',
            default => '',
        };

        while (true) {
            $question = new Question($label.$suffix.': ');
            if ($hidden) {
                $question->setHidden(true)->setHiddenFallback(true);
            }
            $answer = $this->helper->ask($this->input, $this->output, $question);
            $answer = \is_string($answer) ? trim($answer) : '';
            if ('' === $answer && null !== $default) {
                $answer = $default;
            }
            $error = $validate($answer);
            if (null === $error) {
                return $answer;
            }
            $this->line('Error: '.$error);
        }
    }

    /**
     * A numbered list; the answer can be the number or the value.
     *
     * @param array<string, string> $choices value => label
     */
    public function choose(string $label, array $choices, string $default): string
    {
        $values = array_keys($choices);
        $this->line($label.':');
        foreach ($values as $index => $value) {
            $this->line(\sprintf('%d. %s', $index + 1, $choices[$value]));
        }
        $defaultNumber = array_search($default, $values, true);
        $defaultText = false === $defaultNumber ? $default : \sprintf('%d, %s', $defaultNumber + 1, $choices[$default]);

        $answer = $this->ask(\sprintf('Type a number from 1 to %d', \count($values)), $defaultText, static function (string $answer) use ($values, $defaultText, $label): ?string {
            if ($answer === $defaultText || \in_array($answer, $values, true) || (ctype_digit($answer) && isset($values[(int) $answer - 1]))) {
                return null;
            }

            return \sprintf('%s: "%s" is not one of the numbers. Type a number from 1 to %d.', $label, $answer, \count($values));
        });

        if ($answer === $defaultText) {
            return $default;
        }

        return \in_array($answer, $values, true) ? $answer : $values[(int) $answer - 1];
    }

    public function confirm(string $question, bool $default): bool
    {
        $answer = $this->ask($question.' Type yes or no', $default ? 'yes' : 'no', static fn (string $a): ?string => \in_array(strtolower($a), ['yes', 'y', 'no', 'n'], true)
            ? null
            : \sprintf('"%s" is not an answer to this question. Type yes or no.', $a));

        return \in_array(strtolower($answer), ['yes', 'y'], true);
    }
}
