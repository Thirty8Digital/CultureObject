<?php

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ChunkReadFilter implements IReadFilter {

	private $start_row = 0;

	private $end_row = 0;

	/**
	 * Set the list of rows that we want to read.
	 *
	 * @param mixed $start_row  Starting row number
	 * @param mixed $chunk_size Number of rows to import
	 */
	public function setRows( $start_row, $chunk_size ): void {
		$this->start_row = $start_row;
		$this->end_row   = $start_row + $chunk_size;
	}

	public function readCell( string $column, int $row, string $worksheet_name = '' ): bool {
		// Only read the heading row, and the rows that are configured in $this->_start_row and $this->_end_row
		if ( ( $row == 1 ) || ( $row >= $this->start_row && $row < $this->end_row ) ) {
			return true;
		}

		return false;
	}
}
