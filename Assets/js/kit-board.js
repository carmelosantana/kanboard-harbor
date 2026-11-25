(() => {
  'use strict';

  function placeExpandButton(header, cell) {
    const collapsedHeader = header.querySelector('.board-column-collapsed');
    const rotation = cell.querySelector('.board-rotation-wrapper');
    const icon = rotation?.querySelector('.board-toggle-column-view i[aria-label]');
    if (!collapsedHeader || !icon || rotation.hidden) {
      return;
    }

    // Core's delegated toggle uses the column ID. Keep it in the pill so the task cell can stay bare.
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'board-column-expand board-toggle-column-view';
    button.dataset.columnId = header.dataset.columnId;
    button.setAttribute('aria-label', icon.getAttribute('aria-label'));

    const buttonIcon = document.createElement('i');
    buttonIcon.className = icon.className;
    buttonIcon.setAttribute('aria-hidden', 'true');
    button.append(buttonIcon);
    collapsedHeader.append(button);
    rotation.hidden = true;
  }

  function prepareHeaders(board) {
    const interactive = board.querySelector('#board[data-project-id]') !== null;
    for (const lane of board.querySelectorAll('.board-swimlane')) {
      const taskList = lane.querySelector('.board-task-list[data-swimlane-id]');
      if (!taskList) {
        continue;
      }

      const laneId = CSS.escape(taskList.dataset.swimlaneId);
      const headers = board.querySelectorAll(`.board-swimlane-columns-${laneId} > .board-column-header`);
      for (const header of headers) {
        const columnId = CSS.escape(header.dataset.columnId);
        const cell = lane.querySelector(`td.board-column-${columnId}`);
        header.toggleAttribute('data-over-limit', cell?.classList.contains('board-task-list-limit') ?? false);
        if (interactive && cell) {
          placeExpandButton(header, cell);
        }
      }
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const board = document.getElementById('board-container');
    if (!board) {
      return;
    }

    prepareHeaders(board);

    // Core replaces the whole container after a board refresh or a saved move.
    new MutationObserver((changes) => {
      for (const change of changes) {
        for (const node of change.addedNodes) {
          if (node.id === 'board-container') {
            prepareHeaders(node);
          }
        }
      }
    }).observe(board.parentElement, { childList: true });
  });
})();
