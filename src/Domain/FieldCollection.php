<?php
/**
 * Ordered, immutable set of fields.
 *
 * @package Rachnix\CheckoutFields
 */

declare(strict_types=1);

namespace Rachnix\CheckoutFields\Domain;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

defined( 'ABSPATH' ) || exit;

/**
 * Fields sorted by priority (then key, so the order is always the same).
 *
 * @implements IteratorAggregate<int, Field>
 */
final readonly class FieldCollection implements IteratorAggregate, Countable {

	/**
	 * @var list<Field>
	 */
	private array $fields;

	/**
	 * @param list<Field> $fields Fields in any order.
	 */
	public function __construct( array $fields = array() ) {
		usort(
			$fields,
			static fn( Field $a, Field $b ): int => array( $a->priority, $a->key ) <=> array( $b->priority, $b->key )
		);
		$this->fields = $fields;
	}

	/**
	 * Fields of one section.
	 *
	 * @param SectionKey $section Section.
	 */
	public function in_section( SectionKey $section ): self {
		return $this->filter( static fn( Field $field ): bool => $field->section->equals( $section ) );
	}

	/**
	 * Fields shown on the checkout.
	 */
	public function enabled(): self {
		return $this->filter( static fn( Field $field ): bool => $field->enabled );
	}

	/**
	 * @param string $uuid Field UUID.
	 */
	public function find( string $uuid ): ?Field {
		foreach ( $this->fields as $field ) {
			if ( $field->uuid === $uuid ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * @param SectionKey $section Section.
	 * @param string     $key     Field key.
	 */
	public function find_by_key( SectionKey $section, string $key ): ?Field {
		foreach ( $this->fields as $field ) {
			if ( $field->key === $key && $field->section->equals( $section ) ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * @return list<Field>
	 */
	public function to_list(): array {
		return $this->fields;
	}

	public function count(): int {
		return count( $this->fields );
	}

	/**
	 * @return Traversable<int, Field>
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator( $this->fields );
	}

	/**
	 * @param callable(Field): bool $keep Predicate.
	 */
	private function filter( callable $keep ): self {
		return new self( array_values( array_filter( $this->fields, $keep ) ) );
	}
}
