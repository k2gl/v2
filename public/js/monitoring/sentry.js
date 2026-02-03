/**
 * Sentry Monitoring for Vue 3
 * Kanban Board Error Tracking and Performance Monitoring
 */

import * as Sentry from "@sentry/vue";

let sentryInitialized = false;

export function initSentry(app, router) {
  if (sentryInitialized || !import.meta.env.VITE_SENTRY_DSN) {
    return;
  }

  const isProduction = import.meta.env.PROD;
  const tracesSampleRate = isProduction ? 0.1 : 1.0;

  Sentry.init({
    app,
    dsn: import.meta.env.VITE_SENTRY_DSN,
    release: import.meta.env.VITE_APP_VERSION || '1.0.0',
    environment: isProduction ? 'production' : 'development',
    
    integrations: [
      Sentry.browserTracingIntegration({
        router,
        useEffect: true,
        useLocation: true,
        useMatches: true,
      }),
      Sentry.replayIntegration({
        maskAllText: false,
        blockAllMedia: false,
      }),
    ],
    
    tracesSampleRate,
    replaysSessionSampleRate: 0.1,
    replaysOnErrorSampleRate: 1.0,
    
    beforeSend(event, hint) {
      const error = hint.originalException;
      
      if (error?.name === 'NetworkError') {
        return null;
      }
      
      if (error?.message?.includes('ResizeObserver')) {
        return null;
      }
      
      return event;
    },
    
    beforeSendTransaction(transaction) {
      if (transaction.contexts?.response?.status_code === 404) {
        return null;
      }
      return transaction;
    },
    
    debug: !isProduction,
  });

  sentryInitialized = true;
}

export function setUserContext(user) {
  if (!sentryInitialized) return;
  
  Sentry.setUser({
    id: user?.id?.toString(),
    email: user?.email,
    username: user?.name,
    ip_address: '{{auto}}',
  });
}

export function clearUserContext() {
  if (!sentryInitialized) return;
  Sentry.setUser(null);
}

export function setTag(key, value) {
  if (!sentryInitialized) return;
  Sentry.setTag(key, value);
}

export function addBreadcrumb(breadcrumb) {
  if (!sentryInitialized) return;
  Sentry.addBreadcrumb({
    type: breadcrumb.type || 'default',
    category: breadcrumb.category || 'navigation',
    message: breadcrumb.message,
    level: breadcrumb.level || 'info',
    data: breadcrumb.data || {},
  });
}

export function captureException(error, context = {}) {
  if (!sentryInitialized) {
    console.error('Sentry not initialized:', error);
    return;
  }
  
  Sentry.captureException(error, {
    extra: context,
  });
}

export function captureMessage(message, level = 'info') {
  if (!sentryInitialized) {
    console.log(`[${level}]`, message);
    return;
  }
  
  Sentry.captureMessage(message, level);
}

export function startTransaction(name, op) {
  if (!sentryInitialized) {
    return { finish: () => {}, setStatus: () => {} };
  }
  
  return Sentry.startTransaction({
    name,
    op,
  });
}

export function setComponentContext(componentName, props = {}) {
  if (!sentryInitialized) return;
  
  Sentry.setContext('component', {
    name: componentName,
    props: Object.keys(props).reduce((acc, key) => {
      acc[key] = typeof props[key] === 'object' ? '[Object]' : props[key];
      return acc;
    }, {}),
  });
}

export function trackKanbanEvent(eventName, data = {}) {
  addBreadcrumb({
    category: 'kanban',
    message: eventName,
    level: 'info',
    data,
  });
  
  setTag('kanban_event', eventName);
}

export function trackDragDrop(operation, fromColumn, toColumn, taskId) {
  trackKanbanEvent('drag_drop', {
    operation,
    from_column: fromColumn,
    to_column: toColumn,
    task_id: taskId,
  });
}

export function trackApiCall(endpoint, method, duration, status) {
  addBreadcrumb({
    category: 'api',
    message: `${method} ${endpoint}`,
    level: status >= 400 ? 'error' : 'info',
    data: {
      duration_ms: duration,
      status,
    },
  });
}

export function trackMercureEvent(eventType, data = {}) {
  trackKanbanEvent('mercure_event', {
    event_type: eventType,
    ...data,
  });
}
