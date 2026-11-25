(() => {
  'use strict';

  function markOverLimitHeaders(board) {
    for (const lane of board.querySelectorAll('.board-swimlane')) {
      const taskList = lane.querySelector('.board-task-list[data-swimlane-id]');
      if (!taskList) {
        continue;
      }

      const laneId = CSS.escape(taskList.dataset.swimlaneId);
      const headers = board.querySelectorAll(`.board-swimlane-columns-${laneId} > .board-column-header`);
      for (const header of headers) {
        const columnId = CSS.escape(header.dataset.columnId);
        const overLimit = lane.querySelector(`td.board-column-${columnId}.board-task-list-limit`) !== null;
        header.toggleAttribute('data-over-limit', overLimit);
      }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const board = document.getElementById('board-container');
    if (!board) {
      return;
    }

    markOverLimitHeaders(board);

    // Core replaces the whole container after a board refresh or a saved move.
    new MutationObserver((changes) => {
      for (const change of changes) {
        for (const node of change.addedNodes) {
          if (node.id === 'board-container') {
            markOverLimitHeaders(node);
          }
        }
      }
    }).observe(board.parentElement, { childList: true });
  });
})();
