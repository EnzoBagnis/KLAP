/**
 * La forme d'une adresse mail, pour un premier contrôle avant d'appeler l'API : quelque chose,
 * une arobase, un domaine avec un point. C'est l'API qui valide vraiment l'adresse.
 */
export const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
