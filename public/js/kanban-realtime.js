/**
 * Kanban Real-time Updates via Mercure
 * 
 * This module provides real-time updates for the Kanban board
 * using Server-Sent Events (SSE) through Mercure Hub.
 */

class KanbanRealtime {
    constructor(baseUrl, boardId, options = {}) {
        this.baseUrl = baseUrl;
        this.boardId = boardId;
        this.options = {
            autoReconnect: true,
            reconnectInterval: 3000,
            onTaskMoved: (data) => {},
            onTasksReordered: (data) => {},
            onConnected: () => {},
            onDisconnected: () => {},
            onError: (error) => {},
            ...options
        };
        this.eventSource = null;
        this.isConnected = false;
    }

    /**
     * Connect to Mercure Hub and subscribe to board updates
     */
    connect() {
        const topic = `${this.baseUrl}/board/${this.boardId}`;
        const url = new URL('/.well-known/mercure', this.baseUrl);
        url.searchParams.append('topic', topic);
        url.searchParams.append('topic', `${this.baseUrl}/task/*`);

        this.eventSource = new EventSource(url.toString());

        this.eventSource.onopen = () => {
            this.isConnected = true;
            console.log('[Mercure] Connected to real-time updates');
            this.options.onConnected();
        };

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleEvent(data);
            } catch (error) {
                console.error('[Mercure] Failed to parse event:', error);
            }
        };

        this.eventSource.onerror = (error) => {
            this.isConnected = false;
            console.error('[Mercure] Connection error:', error);
            this.options.onError(error);

            if (this.options.autoReconnect && this.eventSource.readyState === EventSource.CLOSED) {
                setTimeout(() => this.connect(), this.options.reconnectInterval);
            }
        };
    }

    /**
     * Handle incoming Mercure events
     */
    handleEvent(data) {
        console.log('[Mercure] Received event:', data.event);

        switch (data.event) {
            case 'task_moved':
                this.options.onTaskMoved(data);
                break;
            case 'tasks_reordered':
                this.options.onTasksReordered(data);
                break;
            default:
                console.log('[Mercure] Unknown event type:', data.event);
        }
    }

    /**
     * Disconnect from Mercure Hub
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            this.isConnected = false;
            this.options.onDisconnected();
        }
    }

    /**
     * Check if connected to Mercure
     */
    isActive() {
        return this.isConnected && this.eventSource?.readyState === EventSource.OPEN;
    }
}

// Export for use in React/Vue/vanilla JS
if (typeof module !== 'undefined' && module.exports) {
    module.exports = KanbanRealtime;
}
