export const PLATFORMS = {
  youtube: {
    id: 'youtube',
    name: 'YouTube',
    icon: 'pi pi-youtube',
    charLimit: 5000,
    mediaTypes: ['video'],
    maxMedia: 1,
    color: '#ff0000'
  },
  facebook: {
    id: 'facebook',
    name: 'Facebook',
    icon: 'pi pi-facebook',
    charLimit: 63206,
    mediaTypes: ['image', 'video'],
    maxMedia: 10,
    color: '#1877f2'
  },
  instagram: {
    id: 'instagram',
    name: 'Instagram',
    icon: 'pi pi-instagram',
    charLimit: 2200,
    mediaTypes: ['image', 'video'],
    maxMedia: 10,
    color: '#e1306c'
  },
  twitter: {
    id: 'twitter',
    name: 'X/Twitter',
    icon: 'pi pi-twitter',
    charLimit: 280,
    mediaTypes: ['image', 'video', 'gif'],
    maxMedia: 4,
    color: '#1d9bf0'
  },
  linkedin: {
    id: 'linkedin',
    name: 'LinkedIn',
    icon: 'pi pi-linkedin',
    charLimit: 3000,
    mediaTypes: ['image', 'video', 'document'],
    maxMedia: 9,
    color: '#0a66c2'
  },
  threads: {
    id: 'threads',
    name: 'Threads',
    icon: 'pi pi-at',
    charLimit: 500,
    mediaTypes: ['image', 'video'],
    maxMedia: 10,
    color: '#999999'
  },
  tiktok: {
    id: 'tiktok',
    name: 'TikTok',
    icon: 'pi pi-video',
    charLimit: 4000,
    mediaTypes: ['video'],
    maxMedia: 1,
    color: '#00f2ea'
  }
}

export const MVP_PLATFORMS = ['youtube', 'facebook', 'instagram']

export function usePlatformLimits() {
  const getPlatform = (platformId) => PLATFORMS[platformId] || null

  const getCharLimit = (platformId) => {
    const platform = getPlatform(platformId)
    return platform?.charLimit || 280
  }

  const getStrictestLimit = (platformIds) => {
    if (!platformIds || platformIds.length === 0) return 280
    return Math.min(...platformIds.map(id => getCharLimit(id)))
  }

  const isOverLimit = (content, platformId) => {
    const limit = getCharLimit(platformId)
    return content.length > limit
  }

  const getCharPercentage = (content, platformId) => {
    const limit = getCharLimit(platformId)
    return Math.min(100, (content.length / limit) * 100)
  }

  const supportsMediaType = (platformId, mediaType) => {
    const platform = getPlatform(platformId)
    return platform?.mediaTypes?.includes(mediaType) || false
  }

  const getMaxMedia = (platformId) => {
    const platform = getPlatform(platformId)
    return platform?.maxMedia || 1
  }

  const getAllPlatforms = () => Object.values(PLATFORMS)

  const getMvpPlatforms = () => MVP_PLATFORMS.map(id => PLATFORMS[id])

  return {
    PLATFORMS,
    MVP_PLATFORMS,
    getPlatform,
    getCharLimit,
    getStrictestLimit,
    isOverLimit,
    getCharPercentage,
    supportsMediaType,
    getMaxMedia,
    getAllPlatforms,
    getMvpPlatforms
  }
}

export default usePlatformLimits
