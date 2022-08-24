<?php

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ChunkReadFilter implements IReadFilter {

	private $startRow = 0;

	private $endRow = 0;

	/**
	 * Set the list of rows that we want to read.
	 *
	 * @param mixed $startRow
	 * @param mixed $chunkSize
	 */
	public function setRows( $startRow, $chunkSize ): void {
		$this->startRow = $startRow;
		$this->endRow   = $startRow + $chunkSize;
	}

	public function readCell( $column, $row, $worksheetName = '' ) {
		// Only read the heading row, and the rows that are configured in $this->_startRow and $this->_endRow
		if ( ( $row == 1 ) || ( $row >= $this->startRow && $row < $this->endRow ) ) {
			return true;
		}

		return false;
	}
}
