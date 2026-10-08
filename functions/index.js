import { defineSecret } from 'firebase-functions/params';
import { onRequest } from 'firebase-functions/v2/https';

import { createLimitedHandler } from './limited-handler.js';

const serviceKey = defineSecret('SMITHARIA_SERVICE_KEY');

export const limitedSite = onRequest(
  { region: 'asia-northeast1', secrets: [serviceKey], timeoutSeconds: 30, maxInstances: 10 },
  createLimitedHandler({ getServiceKey: () => serviceKey.value() })
);
