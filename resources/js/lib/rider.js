/**
 * Rider app helpers: step labels and best-effort GPS.
 */
export const STEP_LABELS = {
  accepted: 'Accept job',
  arrived_at_pickup: 'Arrived at pickup',
  picked_up: 'Parcel picked up',
  in_transit: 'Start trip',
  out_for_delivery: 'Out for delivery',
  delivered: 'Deliver with proof',
  failed_delivery: 'Delivery failed',
  customer_unavailable: 'Customer unavailable',
  rescheduled: 'Try again later',
  returned_to_seller: 'Return to shop',
};

// Steps that need a short explanation from the rider.
export const NOTE_REQUIRED = ['failed_delivery', 'customer_unavailable', 'returned_to_seller', 'declined'];

// The main delivery path shown as a progress tracker.
export const FLOW = ['assigned', 'accepted', 'arrived_at_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];

export const addressLine = (value) => [value?.street, value?.barangay, value?.city, value?.province].filter(Boolean).join(', ');

export const mapsUrl = (value) => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressLine(value))}`;

/**
 * Ask the browser for the rider's position. Resolves to {} when denied, unsupported or slow,
 * so a status update never waits on GPS.
 */
export function currentPosition(timeout = 8000) {
  return new Promise((resolve) => {
    if (!navigator.geolocation) {
      resolve({});
      return;
    }

    // The browser's own timeout does not start until the permission prompt is answered,
    // so keep a hard deadline of our own.
    const fallback = setTimeout(() => resolve({}), timeout + 1000);
    const done = (value) => {
      clearTimeout(fallback);
      resolve(value);
    };

    navigator.geolocation.getCurrentPosition(
      (position) => done({ lat: position.coords.latitude.toFixed(7), lng: position.coords.longitude.toFixed(7) }),
      () => done({}),
      { enableHighAccuracy: true, timeout, maximumAge: 60000 },
    );
  });
}
